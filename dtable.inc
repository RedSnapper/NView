<?php

/**
 * Helper functions for building a DataTables server-side processing SQL query
 *
 * The static functions in this class are helper functions to help build
 * the SQL used in DataTables.
 *
 * See http://datatables.net/usage/server-side for full details on the server-
 * side processing requirements of DataTables.
 *
 */

class DTable {

    static $defaults = array(
        'csvFileName'=>"report.csv",
        'view'=> "dtable_v.ixml",
        'filterview'=> "dtable_f.ixml",
        'multi'=> "dtable_multi.ixml",
        'ajax'=> NULL,
        'columns'=> array(),
        'table'=> NULL,
        'pkey'=> NULL,
        'groupby'=> true,
        'csvfields'=>''
    );

    private $options;
    private $fields;
    private $tables;
    private $restrict;
    private $columns;
    private $filterv;
    private $pkey = NULL;
    private $istatement;  // Statement returned by intial query


    public function __construct($fields = null,$tables=null,$restrict=null,$options = array()){
        $this->fields = $fields;
        $this->tables = $tables;
        $this->restrict = $restrict;
        $this->settings = (object) array_merge((array) self::$defaults,(array) $options);
        $this->groupby = '';
        

        if(!is_null($this->settings->pkey)){
            $this->pkey = $this->settings->pkey;
            if(is_array($this->settings->pkey)){
                $groupby = implode(',',$this->settings->pkey);
                $this->groupby = "group by {$groupby}";
            }else{
                $this->groupby = "group by {$this->settings->pkey}";
            }
        }

        $groupby = $this->settings->groupby ? $this->groupby : "";

        $restriction = self::getRestriction('',$this->restrict);
        $this->istatement = self::sql_exec("select {$this->fields} from {$this->tables} {$restriction} {$groupby}");

        $md = $this->istatement->result_metadata();
        $this->columns = mysqli_fetch_fields($md);
    }

    /**
     * Initalise Dtable based on SQL query 
     * the table based on SQL query.
     * @return NView the current view
     */
    public function init() {
        
        if (isset(Settings::$qst['type']) && Settings::$qst['type'] === 'json') {
            $data = $this->json();
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }elseif(isset(Settings::$qst['type']) && Settings::$qst['type'] === 'csv'){
            $this->csv();
            exit;
        }elseif(isset(Settings::$qst['type']) && Settings::$qst['type'] === 'jsonids'){
            $data = $this->getPrimaryData();
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        } 
        else {
            return $this->setView();
        }
    }

    public function getPrimaryData(){
        $stmt = NULL;
        if(!is_null($this->pkey)){
            $request = $_GET;
            $bindings = array();
            $order = self::order( $request,$this->columns);
            $filter = self::filter( $request, $this->columns, $bindings );
            $restrict = self::getRestriction($filter,$this->restrict);

            if(is_array($this->pkey)){
                $fields = implode(',',$this->pkey);
            }else{
                $fields = $this->pkey;
            }

            $sql = "select {$fields} from {$this->tables} $restrict {$this->groupby}";
            $stmt = self::sql_exec($sql,$bindings);
        }
        $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($this->data_output($stmt,false)));
        $ids = array();
        foreach($it as $v) {
            $ids[]=$v;
        }
        return !is_null($stmt) ? $ids : NULL;

    }

    public function getSettings($options){
        return $this->settings();
    }

    public function getFilters(){
        return $this->filterv;
    }


    /**
     * Initialise the view for datatable generating the columns for 
     * the table based on SQL query.
     * @return NView the current view
     */
	private function setView() {
    
        $v = new NView($this->settings->view);
        $url = is_null($this->settings->ajax) ? $_SERVER['PHP_SELF'] . "?type=json" : $this->settings->ajax;
        $v->set("//h:table/@data-ajaxurl/child-gap()",$url);

        $template = $v->consume("//*[@data-xp='heading']");
        $this->setFilterView();
		
        $md = $this->istatement->result_metadata();
		$finfo = mysqli_fetch_fields($md);
        $cols = array();
		foreach ($finfo as $field) {
            $name = $field->name;
            
            $th = new NView($template);
            $th->set("//*[@data-xp='heading']/child-gap()", $name);
            $th->set("//*[@data-xp='heading']/@data-name", $name);

            if(isset($this->settings->columns[$name]['column'])){
                foreach ($this->settings->columns[$name]['column'] as $key => $value) {
                    $th->set("//*[@data-xp='heading']/@data-$key",json_encode($value));
                }
            }
            

            $v->set("//*[@data-xp='row']/child-gap()",$th);
            $cols[] = $name;
            
		}

        if(!is_null($this->settings->table)){
            foreach ($this->settings->table as $key => $value) {
                if($key =='order'){
                    $count = count($value);
                    for ($i=0; $i < $count; $i++) { 
                        $value[$i][0] = array_search($value[$i][0],$cols);
                    }
                }
                $v->set("//h:table/@data-$key",json_encode($value));
            }
        }

        
		return $v;
	}
    /**
     * Perform the SQL queries needed for an server-side processing requested,
     * utilising the helper functions limit(), order() and
     * filter() among others. The returned array is ready to be encoded as JSON
     * in response to a request, or can be modified if needed before
     * sending back to the client.
     * @return array Server-side processing response array
     */
    public function json() {
        $bindings = array();
        $request = $_GET;
        $groupby = $this->settings->groupby ? $this->groupby : "";

        $recordsTotal= $this->istatement->num_rows;
        $limit = self::limit( $request);
        $order = self::order( $request,$this->columns);
        $filter = self::filter( $request, $this->columns, $bindings );
        $restrict = self::getRestriction($filter,$this->restrict);
        $sql = "select {$this->fields} from {$this->tables} $restrict {$groupby} $order"; // Get total number of filterd records without the limit
        $stmt = self::sql_exec($sql,$bindings);
        $recordsFiltered= $stmt->num_rows;
        $sql = "select {$this->fields} from {$this->tables} $restrict {$groupby} $order $limit "; // Get total number of filterd records with the limit
        $stmt = self::sql_exec($sql,$bindings);

        return array(
            "draw"              => intval( $request['draw'] ),
            "recordsTotal"      => intval( $recordsTotal ),
            "recordsFiltered"   => intval( $recordsFiltered ),
            "data"              => $this->data_output($stmt)
        );
    }

    public function csv(){
        $bindings = array();
        $request = $_GET;
        $limit = self::limit( $request);
        $order = self::order( $request,$this->columns);
        $filter = self::filter( $request, $this->columns, $bindings,true );
        $restrict = self::getRestriction($filter,$this->restrict);
        $fields = $this->fields;
        $groupby = $this->settings->groupby ? $this->groupby : "";
        $csvfields = $this->settings->csvfields;

        $query = "select {$fields},{$csvfields} from {$this->tables} $restrict {$groupby} $order"; // Get total number of filterd records without the limit

        $headings = array();
        $data = array();
        Export::getCSVData($query,$headings,$data);
        $csvhide = array();

        //Get all the hidden csv fields
        if(isset($this->settings->columns)){
            foreach ($this->settings->columns as $key=>$value) {
                if(isset($value['csvhide']) && $value['csvhide']){
                   $csvhide[]=$key; 
                }
            }
        }

        // Remove hidden csv fields from the data
        if(count($csvhide) > 0){
            foreach ($data as &$value) {
                foreach ($csvhide as $csv) {
                   unset($value[$csv]);
                }
            }
            // Remove hidden csv fields from the headings

            foreach ($csvhide as $csv) {
               $pos = array_search($csv,$headings);
               if(is_int($pos)){array_splice($headings,$pos,1);} 
            }
        }

        Export::csvOutput($this->settings->csvFileName,true,$headings,$data);
    }

    

    /**
     * Create the data output array for the DataTables rows
     * 
     *  @param  mysqli_statement $stmt Statement object
     *  @return array Formatted data in a row based format
     */
    private function data_output($stmt,$format=true){

        $variables = array();
        $data = array();
        $meta = $stmt->result_metadata();
        $out = array();
        $columns = $this->columns;
        
        while($field = $meta->fetch_field())
            $variables[] = &$data[$field->name]; // pass by reference
        
        call_user_func_array(array($stmt, 'bind_result'), $variables);

        $rowNum = 1;
        while ($stmt->fetch()) {
            $i = 0;
            $row = array();
            foreach($data as $k=>$v){
                $column = $columns[$i]->name;
                if ( $format && isset( $this->settings->columns[$column]['formatter'] ) ) {
                    $row[] = $this->settings->columns[$column]['formatter']( $v,$rowNum,$data);
                }
                else {
                    $row[] = $v;
                }
                $i++;
            }
            $out[] = $row;
            $rowNum++;
        }
        return $out;
    }

    /**
     * Where clause
     * 
     * Construct the WHERE clause based on filters and user 
     * where clause
     * @param  string $filters      Composed by the filter method
     * @param  string $whereResult  User where clause
     * @return string SQL where clause
     */
    private static function getRestriction($filters='',$whereResult=null){
        $restrict = '';
        if(!is_null($whereResult)){
            $restrict.=$whereResult;
        }
        if($filters !== ''){
            if($restrict !== ''){
                $restrict.= " AND {$filters}";
            }else{
                $restrict.=" {$filters}";
            }
        }
        if ( $restrict !== '' ) {
            $restrict = 'WHERE '.$restrict;
        }
        return $restrict;
    }

    /**
     * Paging
     *
     * Construct the LIMIT clause for server-side processing SQL query
     *
     *  @param  array $request Data sent to server by DataTables
     *  @return string SQL limit clause
     */
    private static function limit ( $request )
    {
        $limit = '';
        if ( isset($request['start']) && $request['length'] != -1 ) {
            $limit = "LIMIT ".intval($request['start']).", ".intval($request['length']);
        }
        return $limit;
    }

    /**
     * Ordering
     *
     * Construct the ORDER BY clause for server-side processing SQL query
     *
     *  @param  array $request Data sent to server by DataTables
     *  @param  array $columns Column information array
     *  @return string SQL order by clause
     */
    private static function order ( $request, $columns )
    {   
        $order = '';
        if ( isset($request['order']) && count($request['order']) ) {
            $orderBy = array();
            for ( $i=0, $ien=count($request['order']) ; $i<$ien ; $i++ ) {
                $columnIdx = intval($request['order'][$i]['column']);
                $requestColumn = $request['columns'][$columnIdx];

                if($columns[ $columnIdx ]->orgname){
                    $column ="{$columns[ $columnIdx ]->table}.{$columns[ $columnIdx ]->orgname}";
                }else{
                    $column ="`{$columns[ $columnIdx ]->name}`";
                }

                if ( $requestColumn['orderable'] == 'true' ) {
                    $dir = $request['order'][$i]['dir'] === 'asc' ?
                        'ASC' :
                        'DESC';
                    $orderBy[] = "$column $dir";
                }
            }
            $order = 'ORDER BY '.implode(', ', $orderBy);
        }
        return $order;
    }

    /**
     * Searching / Filtering
     *
     * Construct the WHERE clause for server-side processing SQL query.
     *
     * NOTE this does not match the built-in DataTables filtering which does it
     * word by word on any field. It's possible to do here performance on large
     * databases would be very poor
     *
     *  @param  array $request Data sent to server by DataTables
     *  @param  array $columns Column names array
     *  @param  array $bindings Array of values for bindings, used in the
     *    sql_exec() function
     *  @return string SQL where clause
     */
    private static function filter ( $request, $columns, &$bindings, $qs = false )
    {
        $globalSearch = array();
        $filters = array();
        $columnSearch = array();
       
        for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
            $requestColumn = $request['columns'][$i];
            $column = "{$columns[ $i ]->table}.{$columns[ $i ]->orgname}";

            if ( $requestColumn['searchable'] == 'true' && 
                isset($request['search']) && 
                $request['search']['value'] != '') {
                $str = $request['search']['value'];
                $val = "%{$str}%";
                self::bind( $bindings,$val, 's');
                if($qs){
                    $globalSearch[] = $column." LIKE '$val'";
                }else{
                    $globalSearch[] = $column." LIKE ?";
                }
            }
        }
        
        if(isset($request['filters'])){
            foreach ($request['filters'] as $key => $value) {
                
                $keys = explode(',',$key);

                $filterOr = array();

                foreach ($keys as $name) {
                    
                    if ( isset($value['multi']) ) {
                        $filter = array();
                        foreach ($value['multi'] as $val) {
                            self::bind( $bindings,$val, 's');
                            if($qs){
                                $filter[] = "'$val'";
                            }else{
                                $filter[] = "?";
                            }
                        }
                        if ( count( $filter ) ) {
                            $filterOr[]= $name . ' in('.implode(',', $filter).')';
                        }
                    }
                }
                $filters[] = "(" . implode(' OR ', $filterOr) . ")";
            }
        }
        
        // Combine the filters into a single string
        $restrict = '';
        if ( count( $globalSearch ) ) {
            $restrict = '('.implode(' OR ', $globalSearch).')';
        }

        if ( count( $filters ) ) {
            if ( count( $globalSearch ) ) {
                $restrict .= ' AND ';
            }
            $restrict .= '('.implode(' AND ', $filters).')';
        }


        return $restrict;
    }

    private function setFilterView(){

        $v = new NView($this->settings->filterview);
        $columns = $this->settings->columns;
        $filters= array();

        $colnames = array();
        foreach ($this->columns as $key => $value) {
            $colnames[] = $value->name;
        }
 

        foreach ($columns as $key => $value) {

            if(isset($value['filter'])){

                $index = array_search($key,$colnames);
                if(is_int($index)){
                    $name = "{$this->columns[ $index ]->table}.{$this->columns[ $index ]->orgname}";
                }else{
                    $name = $key;
                }

                if(isset($value['filter']['fields'])){
                    $name = implode(",",$value['filter']['fields']);
                }

                $label = isset($value['filter']['label']) ? $value['filter']['label'] : $key;

                $filters[] = array(
                    "name"=>$name,
                    "label"=>$label,
                    "value"=>$value['filter']
                );
            }
        }


        foreach ($filters as $filter) {
            if($rx = Settings::$sql->query($filter['value']['query'])){
                if($filter['value']['type'] = 'mutli'){
                    $multi = new NView($this->settings->multi);
                    $ot = $multi->consume('//h:option');

                    while ($f = $rx->fetch_assoc()){
                        $o = new NView($ot);
                        $o->set("//h:option/@value",htmlspecialchars($f['value']));
                        $o->set("//h:option/child-gap()",htmlspecialchars($f['prompt']));
                        $multi->set("//h:select/child-gap()",$o);
                    }
                    $multi->set('//*[@data-xp="label"]/child-gap()',htmlspecialchars($filter['label']));
                    $multi->set('//h:select/@name',"multi:{$filter['name']}");
                    $v->set('//*[@data-xp="filters"]/child-gap()',$multi);
                }
            }else{
                echo Settings::$sql->error;
            }
        }

        $this->filterv = $v;

    }

    /**
     * Execute an SQL query on the database
     * @param  string   $sql SQL query to execute.
     * @param  array    $bindings Array of binding values from bind() to be
     *   used for safely escaping strings.
     * @return mysqli_result  Result object
     */
    private static function sql_exec ($sql=NULL ,$bindings=NULL)
    {
        $stmt = Settings::$sql->prepare($sql);
        if($stmt){
            // Bind parameters
            $length = count( $bindings);
            if ( $length > 0 ) {
                $a_params = array();
                for($i = 0; $i < $length; $i++) {
                  $a_params[] = & $bindings[$i];
                }
                // Need to pass as paramters to bind_param and needs to be by reference
                call_user_func_array( array($stmt, 'bind_param'),$a_params); 
            }
        }else{
            self::fatal(Settings::$sql->error);
        }

        if(!$stmt->execute()){
            self::fatal(Settings::$sql->error);
        }else{
            $stmt->store_result();
        }
        return $stmt;
    }

    /**
     * Create a PDO binding key which can be used for escaping variables safely
     * when executing a query with sql_exec()
     *
     * @param  array &$a    Array of bindings
     * @param  mixed $val   Value to bind
     * @param  string $type PDO field type
     */
    private static function bind ( &$a, $val, $type )
    {
        if(count($a) > 0){
            $a[0].= $type;
        }else{
            $a[0]= $type;
        }
        $a[] = $val;
    }

    /**
     * Throw a fatal error.
     *
     * This writes out an error message in a JSON string which DataTables will
     * see and show to the user in the browser.
     *
     * @param  string $msg Message to send to the client
     */
    private static function fatal ( $msg )
    {
        echo json_encode( array( 
            "error" => $msg
        ));
        exit(0);
    }

}


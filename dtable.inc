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
        'ajax'=> NULL,
        'columns'=> array()
    );

    /**
     * Initalise Dtable based on SQL query 
     * the table based on SQL query.
     * @param  srting $query SQL query
     * @param  srting $where SQL condition
     * @param  array $options Options for dtables
     * @return NView the current view
     */
    public static function init($query = null,$where=null,$options = array()) {
        
        $settings = self::getSettings($options);

        if (isset(Settings::$qst['type']) && Settings::$qst['type'] === 'json') {
            $data = self::json($query, $where,$settings);
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }elseif(isset(Settings::$qst['type']) && Settings::$qst['type'] === 'csv'){
            self::csv($query,$where,$settings);
            exit;
        } 
        else {
            return self::setView($query,$settings);
        }
    }

    public static function getSettings($options){
        return $settings = (object) array_merge((array) self::$defaults,(array) $options);
    }

    /**
     * Initialise the view for datatable generating the columns for 
     * the table based on SQL query.
     * @param  srting $query SQL query
     * @param  array $settings Settings for dtables
     * @return NView the current view
     */
	public static function setView($query = null,$settings=array()) {
        $statement = Settings::$sql->prepare("$query LIMIT 1");
        if($statement){
            $v = new NView($settings->view);
            $url = is_null($settings->ajax) ? $_SERVER['PHP_SELF'] : $settings->$ajax;
            $v->set("//h:table/@data-ajax/child-gap()","{$url}?type=json");
            $template = $v->consume("//*[@data-xp='heading']");
            self::setFilterView($v,$settings);
    		$statement->execute();
    		$result = $statement->get_result();
    		$finfo = $result->fetch_fields();
    		foreach ($finfo as $field) {
                $th = new NView($template);
                $th->set("//*[@data-xp='heading']/child-gap()", $field->name);
    			$v->set("//*[@data-xp='row']/child-gap()",$th);
    		}

            $statement->free_result();
        }else{
            print(__METHOD__ . " " . Settings::$sql->error);
        }
		return $v;
	}
    /**
     * Perform the SQL queries needed for an server-side processing requested,
     * utilising the helper functions limit(), order() and
     * filter() among others. The returned array is ready to be encoded as JSON
     * in response to a request, or can be modified if needed before
     * sending back to the client.
     * @param  string $query       SQL query without the where clause
     * @param  string $whereResult Optional where clause without the word where
     * in the clause
     * @param  array $settings Settings for dtables
     * @return array Server-side processing response array
     */
    public static function json($query = null,$whereResult=null,$settings=array()) {
        $bindings = array();
        $request = $_GET;
        $where = self::getWhere('',$whereResult);
        $result = self::sql_exec("{$query} {$where}"); // Get total amount of records before limit
        $recordsTotal= $result->num_rows;
        $columns = array_map(function($field){return "{$field->table}.{$field->orgname}";},$result->fetch_fields());

        $limit = self::limit( $request);
        $order = self::order( $request,$columns);
        $filter = self::filter( $request, $columns, $bindings );
        $where = self::getWhere($filter,$whereResult);
        $sql = "$query $where $order"; // Get total number of filterd records without the limit
        $result = self::sql_exec($sql,$bindings);
        $recordsFiltered= $result->num_rows;
        $sql = "$query $where $order $limit"; // Get total number of filterd records with the limit
        $result = self::sql_exec($sql,$bindings);

        return array(
            "draw"              => intval( $request['draw'] ),
            "recordsTotal"      => intval( $recordsTotal ),
            "recordsFiltered"   => intval( $recordsFiltered ),
            "data"              => self::data_output($result,$columns,$settings)
        );
    }

    public static function csv($query = null,$whereResult=null,$settings=array()){
        $bindings = array();
        $request = $_GET;
        $where = self::getWhere('',$whereResult);
        $result = self::sql_exec("{$query} {$where}"); // Get total amount of records before limit
        $recordsTotal= $result->num_rows;
        $columns = array_map(function($field){return "{$field->table}.{$field->orgname}";},$result->fetch_fields());

        $limit = self::limit( $request);
        $order = self::order( $request,$columns);
        $filter = self::filter( $request, $columns, $bindings );
        $where = self::getWhere($filter,$whereResult);
        $query = "$query $where $order"; // Get total number of filterd records without the limit
        Export::csv($settings->csvFileName,true,$query);
    }

    /**
     * Create the data output array for the DataTables rows
     * 
     *  @param  mysqli_result $result Result object
     *  @param  array $columns Columns array
     *  @param  array $settings Settings for dtables
     *  @return array Formatted data in a row based format
     */
    private static function data_output($result,$columns,$settings=array()){
        $data=$result->fetch_all();
        $out = array();
        for ( $i=0, $ien=count($data) ; $i<$ien ; $i++ ) {
            $row = array();
            for ( $j=0, $jen=count($columns) ; $j<$jen ; $j++ ) {
                $column = $columns[$j];
                // Is there a formatter?
                if ( isset( $settings->columns[$column]['formatter'] ) ) {
                    $row[$j] = $settings->columns[$column]['formatter']( $data[$i][$j], $data[$i] );
                }
                else {
                    $row[$j] = $data[$i][$j];
                }
            }
            $out[] = $row;
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
    private static function getWhere($filters='',$whereResult=null){
        $where = '';
        if(!is_null($whereResult)){
            $where.=$whereResult;
        }
        if($filters !== ''){
            if($where !== ''){
                $where.= " AND {$filters}";
            }else{
                $where.=" {$filters}";
            }
        }
        if ( $where !== '' ) {
            $where = 'WHERE '.$where;
        }
        return $where;
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
                $column = $columns[ $columnIdx ];
                if ( $requestColumn['orderable'] == 'true' ) {
                    $dir = $request['order'][$i]['dir'] === 'asc' ?
                        'ASC' :
                        'DESC';
                    $orderBy[] = $column.' '.$dir;
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
    private static function filter ( $request, $columns, &$bindings )
    {
        $globalSearch = array();
        $columnSearch = array();
        if ( isset($request['search']) && $request['search']['value'] != '' ) {
            $str = $request['search']['value'];
            for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
                $requestColumn = $request['columns'][$i];
                $column = $columns[ $i ];
                if ( $requestColumn['searchable'] == 'true' ) {
                    $val = "%{$str}%";
                    self::bind( $bindings,$val, 's');
                    $globalSearch[] = $column." LIKE ?";
                }
            }
        }
        // Combine the filters into a single string
        $where = '';
        if ( count( $globalSearch ) ) {
            $where = '('.implode(' OR ', $globalSearch).')';
        }
        return $where;
    }

    private static function setFilterView($v,$settings){
        $mt = $v->consume("//*[@data-xp='multi']");
        $ot = new NView('option_v.ixml');
        $columns = $settings->columns;
        $filters= array();
        foreach ($columns as $key => $value) {
            if(isset($value['filter'])){
                $filters[$key] = $value['filter'];
            }
        }


        foreach ($filters as $filter) {
            if($filter['type'] = 'mutli'){
                $multi = new NView($mt);
                if($result = self::sql_exec($filter['query'])){
                    while ($f =$result->fetch_assoc()){
                        $o = new NView($ot);
                        $o->set("//h:option/@value",$f['value']);
                        $o->set("//h:option/child-gap()",$f['prompt']);
                        $multi->set("//h:select/child-gap()",$o);
                    }
                    $multi->set('//*[@data-xp="label"]/child-gap()',$filter['label']);
                    $v->set('//*[@data-xp="filters"]/child-gap()',$multi);
                }

            }
        }

    }

    /**
     * Execute an SQL query on the database
     * @param  string   $sql SQL query to execute.
     * @param  array    $bindings Array of binding values from bind() to be
     *   used for safely escaping strings.
     * @return mysqli_result  Result object
     */
    public static function sql_exec ($sql=NULL ,$bindings=NULL)
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
        }
        return $stmt->get_result();
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

function debug($content){
    print("<pre>" . print_r($content,true) . "</pre>");
}

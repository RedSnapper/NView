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
		'debug' => false,
		'csvFileName' => "report.csv",
		'view' => "dtable_v.ixml",
		'filterview' => "dtable_f.ixml",
		'selectview' => "dtable_select.ixml",
		'ajax' => NULL,
		'columns' => array(), //each column is an array of information about specialised columns.
		/*
		DTable currently recognises the following values..
			column_name => array ( //column name must match mysql result field.
				'formatter' //(function) allows for modification of a field (eg, turn an id into a link).
				'csvhide'   //(bool) (show/hide this column in the csv file output)
				'json'      //array() //set javascript json values eg visible to false.
				'many'      //(bool) relationship to the primary object - needs a restricted list
				'query'     //(string) query used to generate restricted list values (multi-select etc).
				'filter' => array {
					'type' => //switch null/multi
							'null'   //nulls represent something, not just a lack of data..
							'multi'  // representing if multi-select is to be used.
					'fields' //(array) filter the value on multiple fields, rather than just one.
					'label'  //allows to change the default column name label.
				}
			)
		*/
		'table' => NULL,
		'pkey' => NULL,
		'groupby' => false,
		'csvfields' => '', // This adds to the fields for the csv
		'csvFieldsAll' => null // This overwrites all the fields sent to the csv
	);

	private $settings;
	public $fields;
	public $tables;
	public $restrict;
	public $columns;    //the query return values
	private $filterv;
	public $pkey = NULL;
	public $istatement;  // Statement returned by intial query
	private $request;

	public function __construct($fields = null, $tables = null, $restrict = null, $options = array()) {
		$this->fields = $fields;
		$this->tables = $tables;
		$this->restrict = $restrict;
		$this->settings = (object)array_merge((array)self::$defaults, (array)$options);
		$this->groupby = '';

		$this->pkey = @$this->settings->pkey;
		$this->request = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['json'])) ? json_decode($_POST['json'], true) : $_GET;

		if (!is_null($this->settings->pkey) && $this->settings->groupby) {
			if (is_array($this->settings->pkey)) {
				$groupby = implode(',', $this->settings->pkey);
				$this->groupby = "group by {$groupby}";
			} else {
				$this->groupby = "group by {$this->settings->pkey}";
			}
		}

		$groupby = $this->groupby;
		$restriction = $this->getRestriction('nofilter');
		$this->istatement = $this->sql_exec("select {$this->fields} from {$this->tables} {$restriction} {$groupby} limit 1");

		$md = $this->istatement->result_metadata();
		$this->columns = mysqli_fetch_fields($md);
		$this->setFilters();
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
		} elseif (isset(Settings::$qst['type']) && Settings::$qst['type'] === 'csv') {
			$this->csv();
			exit;
		} elseif (isset(Settings::$qst['type']) && Settings::$qst['type'] === 'jsonids') {
			$data = $this->getPrimaryData();
			header('Content-Type: application/json');
			echo json_encode($data);
			exit;
		} else {
			return $this->setView();
		}
	}

	public function getPrimaryData() {
		$stmt = NULL;
		if (!is_null($this->pkey)) {

			$request = $this->request;;
			$order = self::order($request, $this->columns);
			$restrict = $this->getRestriction();

			if (is_array($this->pkey)) {
				$fields = implode(',', $this->pkey);
			} else {
				$fields = $this->pkey;
			}

			$sql = "select {$fields} from {$this->tables} $restrict {$this->groupby}";
			$stmt = $this->sql_exec($sql, $this->bindings);
		}
		$it = new RecursiveIteratorIterator(new RecursiveArrayIterator($this->data_output($stmt, false)));
		$ids = array();
		foreach ($it as $v) {
			$ids[] = $v;
		}
		return !is_null($stmt) ? $ids : NULL;

	}

	public function getSettings($options) {
		return $this->settings();
	}

	public function getFilters() {
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
		$v->set("//h:table/@data-ajaxurl/child-gap()", $url);

		$template = $v->consume("//*[@data-xp='heading']");
		$this->setFilterView();

		$finfo = $this->columns;
		$cols = array();
		foreach ($finfo as $field) {
			$name = $field->name;

			$th = new NView($template);
			$th->set("//*[@data-xp='heading']/child-gap()", $name);
			$th->set("//*[@data-xp='heading']/@data-name", $name);

			if (isset($this->settings->columns[$name]['json'])) {
				foreach ($this->settings->columns[$name]['json'] as $key => $value) {
					$th->set("//*[@data-xp='heading']/@data-$key", json_encode($value));
				}
			}

			$v->set("//*[@data-xp='row']/child-gap()", $th);
			$cols[] = $name;

		}

		if (!is_null($this->settings->table)) {
			foreach ($this->settings->table as $key => $value) {
				if ($key == 'order') {
					$count = count($value);
					for ($i = 0; $i < $count; $i++) {
						$value[$i][0] = array_search($value[$i][0], $cols);
					}
				}
				$v->set("//h:table/@data-$key", json_encode($value));
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
		$request = $this->request;
		$groupby = $this->groupby;

		$limit = self::limit($request);
		$order = self::order($request, $this->columns);
		$restrict = $this->getRestriction();

		$sql = "select {$this->fields} from {$this->tables} $restrict {$groupby} $order $limit "; // Get total number of filterd records with the limit
		$stmt = $this->sql_exec($sql, $this->bindings);

		return [
			"draw" => intval($request['draw']),
			"recordsTotal" => intval($this->getTotalRecords()),
			"recordsFiltered" => intval($this->getTotalFiltered()),
			"data" => $this->data_output($stmt)
		];
	}

	public function getTotalRecords() {

		$retVal = 0;
		$restriction = $this->getRestriction('nofilter');

		if ($this->settings->groupby) {
			$stmt = $this->sql_exec("select {$this->fields} from {$this->tables} {$restriction} {$this->groupby}");
			$retVal = $stmt->num_rows;
		} else {
			$stmt = $this->sql_exec("select count(*) as count from {$this->tables} {$restriction}");
			$stmt->bind_result($count);
			$stmt->fetch();
			$retVal = $count;
		}
		return $retVal;
	}

	public function getTotalFiltered() {

		$retVal = 0;
		$restriction = $this->getRestriction();

		if ($this->settings->groupby) {
			$sql = "select {$this->fields} from {$this->tables} $restriction {$this->groupby}"; // Get total number of filterd records without the limit
			$stmt = $this->sql_exec($sql, $this->bindings);
			$retVal = $stmt->num_rows;
		} else {
			$sql = "select count(*) from {$this->tables} $restriction"; // Get total number of filterd records without the limit
			$stmt = $this->sql_exec($sql, $this->bindings);
			$stmt->bind_result($count);
			$stmt->fetch();
			$retVal = $count;
		}

		return $retVal;
	}

	public function getAllData() {
		$groupby = $this->groupby;
		$restrict = $this->getRestriction();
		$sql = "select {$this->fields} from {$this->tables} $restrict {$groupby}"; // Get total number of filterd records without the limit
		$stmt = $this->sql_exec($sql, $this->bindings);
		return $this->data_output($stmt);
	}

	public function csv() {
		$request = $this->request;
		$order = self::order($request, $this->columns);
		$restrict = $this->getRestriction('csv');
		$fields = $this->settings->csvFieldsAll ?? $this->fields;
		$groupby = $this->groupby;
		$csvfields = $this->settings->csvfields;

		$csvfields = $csvfields == '' ? $csvfields : ",$csvfields";

		$query = "select {$fields}{$csvfields} from {$this->tables} $restrict {$groupby} $order"; // Get total number of filterd records without the limit

		$headings = array();
		$data = array();
		Export::getCSVData($query, $headings, $data);
		$csvhide = array();

		//Get all the hidden csv fields
		if (isset($this->settings->columns)) {
			foreach ($this->settings->columns as $key => $value) {
				if (isset($value['csvhide']) && $value['csvhide']) {
					$csvhide[] = $key;
				}
			}
		}

		// Remove hidden csv fields from the data
		if (count($csvhide) > 0) {
			foreach ($data as &$value) {
				foreach ($csvhide as $csv) {
					unset($value[$csv]);
				}
			}
			// Remove hidden csv fields from the headings

			foreach ($csvhide as $csv) {
				$pos = array_search($csv, $headings);
				if (is_int($pos)) {
					array_splice($headings, $pos, 1);
				}
			}
		}

		Export::csvOutput($this->settings->csvFileName, true, $headings, $data);
	}

	/**
	 * Create the data output array for the DataTables rows
	 *
	 * @param  mysqli_statement $stmt Statement object
	 * @return array Formatted data in a row based format
	 */
	private function data_output($stmt, $format = true) {

		$variables = array();
		$data = array();
		$meta = $stmt->result_metadata();
		$out = array();
		$columns = $this->columns;

		while ($field = $meta->fetch_field())
			$variables[] = &$data[$field->name]; // pass by reference

		call_user_func_array(array($stmt, 'bind_result'), $variables);

		$rowNum = 1;
		while ($stmt->fetch()) {
			$i = 0;
			$row = array();
			foreach ($data as $k => $v) {
				$column = $columns[$i]->name;
				if ($format && isset($this->settings->columns[$column]['formatter'])) {
					$row[] = $this->settings->columns[$column]['formatter']($v, $rowNum, $data);
				} else {
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
	 * @param  string $filters Composed by the filter method
	 * @param  string $whereResult User where clause
	 * @return string SQL where clause
	 */
	public function getRestriction($type = 'filter') {

		$restrict = '';
		if (!is_null($this->restrict)) {
			$restrict .= $this->restrict;
		}

		if ($type != 'nofilter') {

			$filters = $type == 'csv' ? $this->csvfilters : $this->filters;

			if ($filters !== '') {
				if ($restrict !== '') {
					$restrict .= " AND {$filters}";
				} else {
					$restrict .= " {$filters}";
				}
			}
		}

		if ($restrict !== '') {
			$restrict = 'WHERE ' . $restrict;
		}

		return $restrict;
	}

	/**
	 * Paging
	 *
	 * Construct the LIMIT clause for server-side processing SQL query
	 *
	 * @param  array $request Data sent to server by DataTables
	 * @return string SQL limit clause
	 */
	private static function limit($request) {
		$limit = '';
		if (isset($request['start']) && $request['length'] != -1) {
			$limit = "LIMIT " . intval($request['start']) . ", " . intval($request['length']);
		}
		Settings::esc($limit);
		return $limit;
	}

	/**
	 * Ordering
	 *
	 * Construct the ORDER BY clause for server-side processing SQL query
	 *
	 * @param  array $request Data sent to server by DataTables
	 * @param  array $columns Column information array
	 * @return string SQL order by clause
	 */
	private static function order($request, $columns) {
		$order = '';
		if (isset($request['order']) && count($request['order'])) {
			$orderBy = array();
			for ($i = 0, $ien = count($request['order']); $i < $ien; $i++) {
				$columnIdx = intval($request['order'][$i]['column']);
				$requestColumn = $request['columns'][$columnIdx];

				if ($columns[$columnIdx]->orgname) {
					$column = "{$columns[ $columnIdx ]->table}.{$columns[ $columnIdx ]->orgname}";
				} else {
					$column = "`{$columns[ $columnIdx ]->name}`";
				}

				if ($requestColumn['orderable'] == 'true') {
					$dir = $request['order'][$i]['dir'] === 'asc' ?
						'ASC' :
						'DESC';
					$orderBy[] = "$column $dir";
				}
			}
			$order = 'ORDER BY ' . implode(', ', $orderBy);
		}
		Settings::esc($order);
		return $order;
	}

	public function setFilters() {
		$request = $this->request;
		$columns = $this->columns;
		$bindings = array();
		$globalSearch = array();
		$globalSearchCSV = array();
		$filterArray = array();

		if (isset($request['columns'])) {

			for ($i = 0, $ien = count($request['columns']); $i < $ien; $i++) {
				$requestColumn = $request['columns'][$i];
				$table = $columns[ $i ]->table;
				$columnName = $columns[ $i ]->orgname;
				$column = "$table.$columnName";

				// Make sure we are not searching on a derived column
				if ($table !== '' && $columnName !== '') {
					if ($requestColumn['searchable'] == 'true' && isset($request['search']) && $request['search']['value'] != '') {
						$str = $request['search']['value'];
						Settings::esc($str);
						$val = "%{$str}%";
						self::bind($bindings, $val, 's');
						$globalSearchCSV[] = $column . " LIKE '$val'";
						$globalSearch[] = $column . " LIKE ?";
					}
				}
			}
		}

		if (isset($request['filters'])) {

			$columnNames = array_map(function ($i) {
				return $i->name;
			},
				$columns);
			foreach ($request['filters'] as $key => $filterinfo) {

				$type = array_keys($filterinfo)[0];
				$filter = array();
				$keys = explode(',', $key);

				foreach ($keys as $name) {
					$filter['type'] = $type;
					$filter['names'][] = $name;
					$filter['range'] = array();
					$filter['rangevalues'] = array();
					$filter['derived'] = in_array($name, $columnNames);
					$filterValue = $filterinfo[$type];

					if ($type == 'multi') {
						foreach ($filterValue as $val) {
							self::bind($bindings, $val, 's');
							Settings::esc($val);
							$filter['range'][] = "?";
							$filter['rangevalues'][] = "'$val'";
						}
					}
					if ($type == 'null') {
						$filter['value'] = $filterValue[0];
					}
				}
				array_push($filterArray, $filter);
			}
		}

		$this->bindings = $bindings;
		$this->filters = self::composeFilters($globalSearch, $filterArray);
		$this->csvfilters = self::composeFilters($globalSearchCSV, $filterArray, true);
		//$this->having = self::composeFilters($globalSearch,$filters);
		//$this->havingvalues = self::composeFilters($globalSearchCSV,$filtersCSV);

	}

	private static function composeFilters($search = array(), $filtersArray = array(), $values = false, $derived = false) {
		// Combine the filters into a single string
		$filters = array();
		foreach ($filtersArray as $filterinfo) {

			$filterOr = array();

			if ($filterinfo['type'] == 'multi') {
				$filter = $values ? $filterinfo['rangevalues'] : $filterinfo['range'];
				foreach ($filterinfo['names'] as $name) {
					$filterOr[] = $name . ' in(' . implode(',', $filter) . ')';
				}
			}
			if ($filterinfo['type'] == 'null') {
				$null = '';
				if ($filterinfo['value'] == '1') {
					$null = 'is not null';
				}
				if ($filterinfo['value'] == '0') {
					$null = 'is null';
				}
				if ($null != '') {
					foreach ($filterinfo['names'] as $name) {
						$filterOr[] = "$name $null";
					}
				}
			}
			if (count($filterOr) > 0) {
				$filters[] = "(" . implode(' OR ', $filterOr) . ")";
			}

		}

		$restrict = '';
		if (count($search)) {
			$restrict = '(' . implode(' OR ', $search) . ')';
		}

		if (count($filters)) {
			if (count($search)) {
				$restrict .= ' AND ';
			}
			$restrict .= '(' . implode(' AND ', $filters) . ')';
		}

		return $restrict;
	}

	public function setFilterView() {

		$v = new NView($this->settings->filterview);
		$columns = $this->settings->columns;
		$filters = array();

		$colnames = array();
		foreach ($this->columns as $key => $value) {
			$colnames[] = $value->name;
		}

		foreach ($columns as $key => $value) {
			if (isset($value['filter'])) {
				$index = array_search($key, $colnames);
				if (is_int($index)) {
					$name = "{$this->columns[ $index ]->table}.{$this->columns[ $index ]->orgname}";
					if ($name == ".") {
						$name = $key;
					}
				} else {
					$name = $key;
				}

				if (isset($value['filter']['fields'])) {
					$name = implode(",", $value['filter']['fields']);
				}


				$label = isset($value['filter']['label']) ? $value['filter']['label'] : $key;

				$filters[] = array(
					"name" => $name,
					"label" => $label,
					"value" => $value['filter'],
					"values" => $value['filter']['values'] ?? [],
					"query" => @$value['query']
				);
			}
		}

		foreach ($filters as $filter) {
			if ($rx = Settings::$sql->query($filter['query'])) {
				$type = $filter['value']['type'];

				switch ($type) {
					default:
						$this->selectView($rx, $filter, $type, $v);
				}
			} else {
				echo Settings::$sql->error;
			}
		}

		$this->filterv = $v;

	}

	public function selectView($rx, $filter, $type, $v) {
		$select = new NView($this->settings->selectview);
		$ot = $select->consume('//h:option');

		if ($type == 'multi') {
			$select->set('//h:select/@multiple', "true");
		} else {
			$o = new NView($ot);
			$o->set("//h:option/child-gap()", ' ');
			$select->set("//h:select/child-gap()", $o);
		}

		while ($f = $rx->fetch_assoc()) {
			$o = new NView($ot);
			$o->set("//h:option/@value", htmlspecialchars($f['value']));
			$o->set("//h:option/child-gap()", htmlspecialchars($f['prompt']));
			$select->set("//h:select/child-gap()", $o);
		}

		foreach ($filter['values'] as $val) {
			$select->set("//h:select/h:option[@value='" . $val . "']/@selected","selected");
		}

		$select->set('//*[@data-xp="label"]/child-gap()', htmlspecialchars($filter['label']));
		$select->set('//h:select/@name', "$type:{$filter['name']}");

		$v->set('//*[@data-xp="filters"]/child-gap()', $select);
	}

	/**
	 * Execute an SQL query on the database
	 * @param  string $sql SQL query to execute.
	 * @param  array $bindings Array of binding values from bind() to be
	 *   used for safely escaping strings.
	 * @return mysqli_result  Result object
	 */
	private function sql_exec($sql = NULL, $bindings = NULL) {
		$stmt = Settings::$sql->prepare($sql);
		if ($stmt) {
			// Bind parameters
			$length = count($bindings);
			if ($length > 0) {
				$a_params = array();
				for ($i = 0; $i < $length; $i++) {
					$a_params[] = &$bindings[$i];
				}
				// Need to pass as paramters to bind_param and needs to be by reference
				call_user_func_array(array($stmt, 'bind_param'), $a_params);
			}
		} else {
			self::fatal(Settings::$sql->error);
		}
		if ($this->settings->debug) {
			$bnd = print_r($bindings, true);
			print("\n==== SQL ====\n$sql\n\n==== Bindings ====\n$bnd\n\n");
		} else {
			if (!$stmt->execute()) {
				self::fatal(Settings::$sql->error);
			} else {
				$stmt->store_result();
			}
		}
		return $stmt;
	}

	/**
	 * Create a PDO binding key which can be used for escaping variables safely
	 * when executing a query with sql_exec()
	 *
	 * @param  array &$a Array of bindings
	 * @param  mixed $val Value to bind
	 * @param  string $type PDO field type
	 */
	private static function bind(&$a, $val, $type) {
		if (count($a) > 0) {
			$a[0] .= $type;
		} else {
			$a[0] = $type;
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
	private static function fatal($msg) {
		echo json_encode(array(
							 "error" => $msg
						 ));
		exit(0);
	}

}


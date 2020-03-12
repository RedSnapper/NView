<?php
namespace RS\NView;

class Export {
	static function csv($fn="data.csv",$addheaders=true,$sql="select a,b,c") {
		$headings = array();
		$data = array();
		self::getCSVData($sql,$headings,$data);
		self::csvOutput($fn,$addheaders,$headings,$data);
	}

	static function csvOutput($fn="data.csv",$addheaders=true,$headings=array(),$data=array()) {
		if($addheaders) {
			header("Content-Type: text/csv; charset=UTF-16LE");
			header("Content-Disposition: attachment; filename=".$fn );
		}
		$output = fopen("php://output","w");
		ob_start();
		fputcsv($output,$headings,chr(9));
		foreach ($data as $value) {
			fputcsv($output, $value,chr(9));	
		}
		fclose($output);
		$csv = ob_get_clean();
		$csv = mb_convert_encoding($csv,'UTF-16LE','UTF-8');
		echo chr(255) . chr(254) . $csv; // adding BOM 
	}

	static function getCSVData($query,&$headings=array(),&$data=array()){
		$data = array();
        if ($rx = Settings::$sql->query($query)) {

        	$finfo = $rx->fetch_fields();
			$headings = array();
			while ($finfo = $rx->fetch_field()) {
				$headings[]= $finfo->name;
			}
            while ($f = $rx->fetch_assoc()) {
                $data[]= $f;
            }
            $rx->close();
        }else{
            echo Settings::$sql->error;
        }
	}

}

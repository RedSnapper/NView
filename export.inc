<?php
mb_internal_encoding('UTF-8');

class Export {
	static function csv($fn="data.csv",$addheaders=true,$sql="select a,b,c") {
		if($addheaders) {
			header("Content-Type: text/csv; charset=UTF-16LE");
			header("Content-Disposition: attachment; filename=".$fn );
		}
		if ($rx = Settings::$sql->query($sql)) {
			$output = fopen("php://output","w");
			ob_start();
			$finfo = $rx->fetch_fields();
			$headings = array();
			while ($finfo = $rx->fetch_field()) {
				$headings[]= $finfo->name;
			}
			fputcsv($output,$headings,chr(9));
			while ($f = $rx->fetch_assoc()) {
				fputcsv($output, $f,chr(9));	
			}
			$rx->close();
			fclose($output);
			$csv = ob_get_clean();
			$csv = mb_convert_encoding($csv,'UTF-16LE','UTF-8');
			echo chr(255) . chr(254) . $csv; // adding BOM 
		}else{
			echo Settings::$sql->error;
		}
	}
}

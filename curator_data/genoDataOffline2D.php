<?php
/**
 * 2D Genotype data importer
 * 
 * PHP version 5.3
 * Prototype version 1.5.0
 * 
 * @category PHP
 * @package  T3
 * @author   Clay Birkett <cbirkett@gmail.com>
 * @license  http://triticeaetoolbox.org/wheat/docs/LICENSE Berkeley-based
 * @version  GIT: 2
 * @link     http://triticeaetoolbox.org/wheat/curator_data/genotype_data_upload.php
 *  
 * pieces of import code by Julie's team @ iowaStateU  

 * 07/18/2012 cbirkett convert AGCT to Illumina base calls
 * 04/17/2011 cbirkett Replace loop control "next" with "continue"
 * 04/17/2011 cbirkett allow E_NOTICE errors
 * 02/08/2011 cbirkett	Ignore space characters in line input file
 * 10/25/2011  JLee   Ignore "cut" portion of input file 
 * 10/17/2011 JLee  Add username and resubmission entry to input file log table
 * 10/17/2011 JLee  Create of input file log entry
 * 4/11/2011 JLee   Add ability to handle zipped data files

 * Written By: John Lee
 */
$progPath = realpath(dirname(__FILE__).'/../').'/';

require "$progPath" . "includes/bootstrap_curator.inc";
require "$progPath" . "curator_data/lineuid.php";
require_once "$progPath" . "includes/email.inc";

ini_set('auto_detect_line_endings', 1);

$num_args = $_SERVER["argc"];
$fnames = $_SERVER["argv"];
$lineTransFile = $fnames[1];
$gDataFile = $fnames[2];
$emailAddr = $fnames[3];
$urlPath = $fnames[4];
$userName = $fnames[5];
$filename = stristr($gDataFile, basename($gDataFile));

$error_flag = 0;
$lineExpHash = array ();
$lineDsHash = array ();
$curTrialCode = '';
$gName = '';

echo "Start time - ". date("m/d/y : H:i:s", time()) ."\n"; 
echo "Translate File - ". $lineTransFile. "\n";
echo "Genotype Data File - ". $gDataFile. "\n";
echo "URL - " . $urlPath . "\n";
echo "Email - ". $emailAddr."\n";

/**
 * look for unambiguous base at location specified by offset
 * http://www.illumina.com/documents/products/technotes/technote_topbot.pdf
 * 
 * @param string $snp    marker sequence
 * @param number $offset position in squence
 * 
 * @return number (0=not found 1=found)
 */
function findUnambig ($snp, $offset)
{
    global $strand, $a_allele, $b_allele;
    $pattern = "/([A-Z])\/([A-Z])/";
    if (preg_match($pattern, $snp, $match)) {
        $snp_pos1 = $match[1];
        $snp_pos2 = $match[2];
    } else {
        echo "Error: bad SNP sequence $snp\n";
    }
    if ($offset > 0) {
        $pattern = "/([A-Z])[A-Z]{" . $offset . "}\[[A-Z]\/[A-Z]\][A-Z]{" . $offset . "}([A-Z])/";
    }
    if (preg_match($pattern, $snp, $match)) {
        $found = 1;
        if (($match[1] == "A") &&  (($match[2] == "C") || ($match[2] == "G"))) {
            $strand = "TOP";
            $a_allele = $match[1];
            $b_allele = $match[2];
        } elseif (($match[2] == "A") &&  (($match[1] == "C") || ($match[1] == "G"))) {
            $strand = "TOP";
            $a_allele = $match[2];
            $b_allele = $match[1];
        } elseif (($match[1] == "T") &&  (($match[2] == "C") || ($match[2] == "G"))) {
            $strand = "BOT";
            $a_allele = $match[1];
            $b_allele = $match[2];
        } elseif (($match[2] == "T") &&  (($match[1] == "C") || ($match[1] == "G"))) {
            $strand = "BOT";
            $a_allele = $match[2];
            $b_allele = $match[1];
        } else {
            $found = 0;
        }
    } else {
        echo "Error: not enough flanking sequence $snp offset=$offset\n";
    }
    if ($offset > 0) {
        if (($match[1] == "A") || ($match[1] == "T")) {
            $strand = "TOP";
            $a_allele = $snp_pos1;
            $b_allele = $snp_pos2;
        }
        if (($match[2] == "A") || ($match[2] == "T")) {
            $strand = "BOT";
            $a_allele = $snp_pos2;
            $b_allele = $snp_pos1;
        }
    }
    if ($found) {
        return 0;
    } else {
        return 1;
    }
}

/**
 * step through the offset until unambigous base found
 * 
 * @param string $seq       sequence from marker table
 * @param string $marker_ab snp as defined by the A_allele B_allele in marker table
 * 
 * @return NULL
 */
function findIllumina ($seq, $marker_ab)
{
    global $strand, $a_allele, $b_allele;
    $strand = "";
    $a_allele = "";
    $b_allele = "";
    $ambiguous = 1;
    $offset = 0;
    while ($ambiguous) {
        $ambiguous = findUnambig($seq, $offset);
        $offset++;
        if ($offset > 10) {
            echo "Error: offset is greater than 10\n";
            break;
            //exitFatal($errFile,  "No unambiguous base pair found $seq");
        }
    }
    $offset--;
    echo "$marker $seq $strand $a_allele $b_allele $offset\n";
    $tmp = $a_allele . $b_allele;
    if ($tmp != $marker_ab) {
        echo "Warning: from marker table A_allele B_allele = $marker_ab\n";
    }
}

/**
 * convert ACTG to Illumina AB format
 * 
 * @param string $alleles ACTG base calls
 * 
 * @return string converted base calls
 */
function convert2Illumina ($alleles)
{
    global $a_allele, $b_allele;
    $results = "";
    if (($a_allele == "") || ($b_allele == "")) {
        echo "Error: A allele and B allele undetermined\n";
    } elseif ($alleles == $a_allele) {
        $results = 'AA';
    } elseif ($alleles == $b_allele) {
        $results = 'BB';
    } elseif ($alleles == 'N') {
        $results = '--';
    } else {
        echo "Error: allele is not valid SNP $a_allele, $b_allele, $alleles\n";
    }
    return $results;
} 

$linkID = connect(); 

$target_Path = substr($lineTransFile, 0, strrpos($lineTransFile, '/')+1);
$tPath = str_replace('./', '', $target_Path);

$errorFile = $target_Path."importError.txt";
echo $errorFile."\n";
if (($errFile = fopen($errorFile, "w")) === false) {
    echo "Unable to open the error log file.";
    exit(1);
}

//get marker seq
$sql = "SELECT marker_name, A_allele, B_allele, sequence from markers where sequence is not NULL";
$res = mysql_query($sql) or die("Database Error: setting lookup - ". mysql_error()."\n\n$sql");
while ($row = mysql_fetch_array($res)) {
    $marker_name = $row['marker_name'];
    $marker_snp[$marker_name] = $row['A_allele'] . $row['B_allele'];
    $marker_seq[$marker_name] = $row['sequence'];
}

// Testing for non-processing
//exit (1);
// ******* Email Stuff *********
//senders name
$Name = "Genotype Data Importer"; 
//senders e-mail adress
$sql ="SELECT value FROM  settings WHERE  name = 'capmail'";
$res = mysql_query($sql) or die("Database Error: setting lookup - ". mysql_error()."\n\n$sql");
$rdata = mysql_fetch_assoc($res);
$myEmail=$rdata['value'];
$mailheader = "From: ". $Name . " <" . $myEmail . ">\r\n"; //optional headerfields
$subject = "Genotype import results";

//Check inputs
 if ($lineTransFile == "") {
    exitFatal($errFile,  "No Line Translation File Uploaded.");
}  
  
if ($gDataFile == "") {
    exitFatal($errFile, "No Genotype Data Uploaded.");
}  

if ($emailAddr == "") {
    echo "No email address. \n";
    exit (1);
}  

// Check for zip file
if (strpos($gDataFile, ".zip") == true) {
	echo "Unzipping the genotype data file...\n";
	$zip = new ZipArchive;
	$zip->open($gDataFile) || exitFatal($errFile, "Unable to open zip file, please check zip format.");
	$gName = $zip->getNameIndex(0);
	$zip->extractTo($target_Path) || exitFatal($errFile, "Failed to extract file from the zip file.");
	$zip->close()  || exitFatal($errFile, "Failed to close zip file.");
	$gDataFile = $target_Path . $gName;
	echo "Genotype data unzipping done.\n";
}

/* Read the file */
if (($reader = fopen($lineTransFile, "r")) == false) {
    exitFatal($errFile, "Unable to access translate file.");
}
            
 // Check first line for header information
if (($line = fgets($reader)) == fales) {
    exitFatal($errFile, "Unable to locate header names on first line of file.");
}     

echo "Processing line translation file...\n";

$header = str_getcsv($line,"\t");
 // Set up header column; all columns are required
$lineNameIdx = implode(find("Line Name", $header),"");
$trialCodeIdx = implode(find("Trial Code", $header),"");
echo "Using Line Name column = $lineNameIdx, Trial Code column = $trialCodeIdx\n";
            
if (($lineNameIdx == "")||($trialCodeIdx == "")) {
   exitFatal($errFile,"ERROR: Missing one of the required columns in Line Translation file. Please correct it and try upload again.");
}
  
// Store individual records
$num = 0;
$linenumber = 0;
while(($line = fgets($reader)) !== false) { 
  $linenumber++;
  $origline = $line;
    chop($line, "\r");
    if ((stripos($line, '- cut -') > 0 )) break;

    if (preg_match('/ /',$line)) {
      echo "removing illegal blank character from $line";
      $line = preg_replace('/ /','',$line);
    }
    if (strlen($line) < 2) continue;
    if (feof($reader)) break;
    if (empty($line)) continue;
                
    $data = str_getcsv($line,"\t");
                        
    //Check for junk line
    if (count($data) != 2) {
      //exitFatal ($errFile, "ERROR: Invalid entry in Line Translation file - '$line' ");
      $parsed = print_r($data, true);
      exitFatal($errFile, "ERROR: Invalid entry in line number $linenumber of Line Translation file.\n Text of line: '$origline'\nContents parsed as: $parsed");
    }
    $trialCodeStr = $data[$trialCodeIdx];
    $lineStr = $data[$lineNameIdx];
                
    //echo  $lineStr . " - ". $trialCodeStr. "<br>"; 
    // Trial Code processing
    if (($curTrialCode != $trialCodeStr) && ($trialCodeStr != '')) {
        $sql = "SELECT experiment_uid FROM experiments WHERE trial_code = '$trialCodeStr'";
	$res = mysql_query($sql)
            or exitFatal($errFile, "Database Error: Experiment uid lookup - ".mysql_error());
        if ($row = mysql_fetch_assoc($res)) {
          $exp_uid = implode(",",$row);
        } else {
	  exitFatal($errFile, "not found - $sql");
        }
        
	$sql = "SELECT datasets_experiments_uid FROM datasets_experiments WHERE experiment_uid = '$exp_uid'";            
	$res = mysql_query($sql)
            or exitFatal($errFile, "Database Error: Dataset experiment uid lookup - ".mysql_error());
        if ($row = mysql_fetch_assoc($res)) {
          $de_uid=implode(",",$row);
	} else {
          exitFatal($errFile, "not found - $sql");
        }

        $curTrialCode = $trialCodeStr;
        $num++;
    }
    $lineExpHash[$lineStr] = $exp_uid;
    $lineDsHash[$lineStr] = $de_uid;
}    
fclose($reader);   
echo "Line translation file processing done. $num\n";

echo "Start genotyping record creation process...\n";
//Process Genotype data
/* start reading the input */
//echo "genotype file - " . $gDataFile . "<br>";

/* Read the file */
if (($reader = fopen($gDataFile, "r")) == false) {
    exitFatal($errFile, "Unable to access genotype data file.");
}
        
//Advance to data header area
while(!feof($reader))  {
    $line = fgets($reader);
    if (preg_match("/SNP/",$line)) {
      echo "Header line found\n";
      break;
    } else {
      exitFatal($errFile, "Could not find header in $gDataFile $line.");    
    }
}
        
if (feof($reader)) {
    exitFatal($errFile, "Unable to locate genotype header line.");
}

//Get column location  
$header = str_getcsv($line,"\t");
$num = count($header);
for ($x = 0; $x < $num; $x++) {
  switch ($header[$x] ) {
	case 'SNP':
	 	$nameIdx = $x;
		$dataIdx = $x + 1;
		break;
  }
}
                     
$rowNum = 0;
$line_name = "qwerty";
$errLines = 0;
$data = array();
    
while (!feof($reader))  {
    // If we have too many errors stop processing - something is wrong
    If ($errLines > 1000) {
       exitFatal($errFile, "ERROR: Too many import lines have problem."); 
    }    
    $line = fgets($reader);
    if (strlen($line) < 2) continue;
    if (empty($line)) continue;
    if (feof($reader)) break;
    $data = str_getcsv($line,"\t");
    $marker = $data[$nameIdx];
    $num = count($data);		// number of fields
    echo "working on marker $marker with $num of lines\n";

    if (isset($marker_seq[$marker])) {
      $seq = $marker_seq[$marker];
      $marker_ab = $marker_snp[$marker];
      findIllumina($seq, $marker_ab);
    } else {
      echo "Warning: no marker sequence found for $marker\n";
      $seq = "unknown";
    }
    
    /* check if marker is EST synonym, if not found, then check name */
    $sql ="SELECT ms.marker_uid FROM  marker_synonyms AS ms WHERE ms.value='$marker'";
    $res = mysql_query($sql) or exitFatal($errFile, "Database Error: Marker synonym lookup - ". mysql_error()."\n\n$sql");
    // fwrite($errFile,$sql);
    $rdata = mysql_fetch_assoc($res);
    $marker_uid=$rdata['marker_uid'];
    if (empty($marker_uid)) {
    	$sql = "SELECT m.marker_uid FROM  markers AS m WHERE m.marker_name ='$marker'";
    	$res = mysql_query($sql) or exitFatal($errFile, "Database Error: Marker lookup - ". mysql_error()."\n\n$sql");
    	// fwrite($errFile,$sql);
    	if (mysql_num_rows($res) < 1) {
    		$markerflag = 1;
    		$msg = 'ERROR:  marker not found '.$marker.'\n';
    		fwrite($errFile, $msg);
    		$errLines++;
    		continue;
    	} else {
    		$rdata = mysql_fetch_assoc($res);
    		$marker_uid=$rdata['marker_uid'];
    	}
    }
    
    $rowNum++;		// number of lines
    $markerflag = 0;        //flag for checking marker existence
    $data_pt = 0;
    $sql = "SET AUTOCOMMIT=0";
    $res = mysql_query($sql) or exitFatal($errFile, "Database Error: - ". mysql_error()."\n\n$sql");
    $sql = "SET foreign_key_checks=0";
    $res = mysql_query($sql) or exitFatal($errFile, "Database Error: - ". mysql_error()."\n\n$sql");
    $sql = "SET unique_checks=0";
    $res = mysql_query($sql) or exitFatal($errFile, "Database Error: - ". mysql_error()."\n\n$sql");
    $sql = "START TRANSACTION";
    $res = mysql_query($sql) or exitFatal($errFile, "Database Error: - ". mysql_error()."\n\n$sql");
    for ($data_pt = $dataIdx; $data_pt < $num; $data_pt++) {
      $line_name = $header[$data_pt];

      if ($markerflag == 0) {
	  /* get line record ID */ 
	  //echo $line_name,"\n";
            $msg = "line name = " . $line_name. "\n";
	    // fwrite($errFile, $msg);
            $line_uid = get_lineuid($line_name);
            if ($line_uid == false) {
                $msg = $line_name . " cannot be found, upload stopped\n";
                exitFatal($errFile, $msg);
            }
            $line_uid = implode(",",$line_uid);
            if (isset($lineExpHash[$line_name])) {
              $exp_uid = $lineExpHash[$line_name];
	    } else {
              $msg = "missing from experiments $line_name $line_uid" . "\n";
	      fwrite($errFile, $msg);
            }
 	    if (isset($lineDsHash[$line_name])) {
              $de_uid = $lineDsHash[$line_name];
            } else {
	      $msg = "missing from dataset experiments $line_name $line_uid" . "\n";
	      fwrite($errFile, $msg);
            }
            //echo "de_uid = " . $exp_uid . "<br>";
//        }
				
        /* get thtbase_uid. If null, then we have to create this ID */
	    $sql = "SELECT tht_base_uid FROM tht_base WHERE experiment_uid= '$exp_uid' AND line_record_uid='$line_uid' ";
	    $rtht = mysql_query($sql) or exitFatal($errFile, "Database Error: tht_base lookup - ". mysql_error() . ".\n\n$sql");
	    // fwrite($errFile,$sql);
	    $rqtht = mysql_fetch_assoc($rtht);
	    $tht_uid = $rqtht['tht_base_uid'];
				
	    if (empty($tht_uid)) {
            $sql ="INSERT INTO tht_base (line_record_uid, experiment_uid, datasets_experiments_uid, updated_on, created_on)
					VALUES ('$line_uid', $exp_uid, $de_uid, NOW(), NOW())" ;
            $res = mysql_query($sql) or exitFatal($errFile, "Database Error: tht_base insert failed - ". mysql_error() . ".\n\n$sql");
            $sql = "SELECT tht_base_uid FROM tht_base WHERE experiment_uid = '$exp_uid' AND line_record_uid = '$line_uid'";
            $rtht=mysql_query($sql) or exitFatal($errFile, "Database Error: post tht_base insert - ". mysql_error(). ".\n\n$sql");
            $rqtht=mysql_fetch_assoc($rtht);
            $tht_uid=$rqtht['tht_base_uid'];
        }
					
    	/* get the genotyping_data_uid */
    	$sql ="SELECT genotyping_data_uid FROM genotyping_data WHERE marker_uid=$marker_uid AND tht_base_uid=$tht_uid ";
    	$rgen=mysql_query($sql) or exitFatal($errFile, "Database Error: genotype_data lookup - ". mysql_error(). ".\n\n$sql");
    	$rqgen=mysql_fetch_assoc($rgen);    
    	$gen_uid=$rqgen['genotyping_data_uid'];
				
    	if (empty($gen_uid)) {
    	    $sql="INSERT INTO genotyping_data (tht_base_uid, marker_uid, updated_on, created_on)
					VALUES ($tht_uid, $marker_uid, NOW(), NOW())" ;
            $res = mysql_query($sql) or exitFatal($errFile, "Database Error: genotype_data insert - ". mysql_error() . ".\n\n$sql");
            $sql ="SELECT genotyping_data_uid FROM genotyping_data WHERE marker_uid = $marker_uid AND tht_base_uid=$tht_uid ";
            $rgen=mysql_query($sql) or exitFatal($errFile, "Database Error: post genotype_data lookup - ". mysql_error(). ".\n\n$sql");
            $rqgen=mysql_fetch_assoc($rgen);
            $gen_uid=$rqgen['genotyping_data_uid'];
        }
		// echo "gen_uid".$gen_uid."\n";
		/* Read in the rest of the variables */
        $alleles = $data[$data_pt];
        $allele1 = substr($data[$data_pt],0,1);
	$allele2 = substr($data[$data_pt],1,1);
        if (($alleles == 'A') || ($alleles == 'C') || ($alleles == 'T') || ($alleles == 'G') || ($alleles == 'N')) {
          $results = convert2Illumina($alleles);
          if ($results == "") {
            echo "Error: could not convert to Illumina AB format\n";
            exitFatal($errFile,  "No unambiguous base pair found for $marker\n$seq");
          } else {
            $alleles = $results;
            $allele1 = substr($alleles,0,1);
            $allele2 = substr($alleles,1,1);
          }
        }
	if (($alleles == 'AA') || ($alleles == 'BB') || ($alleles == '--') || ($alleles == 'AB') || ($alleles == 'BA')) {
            $result =mysql_query("SELECT genotyping_data_uid FROM alleles WHERE genotyping_data_uid = $gen_uid") or exitFatal($errFile, "Database Error: gd lookup $sql");
            $rgen=mysql_num_rows($result);
            if ($rgen < 1) {
		      $sql = "INSERT INTO alleles (genotyping_data_uid,allele_1,allele_2,
						updated_on, created_on)
						VALUES ($gen_uid,'$allele1','$allele2', NOW(), NOW()) ";
            } else {
		      $sql = "UPDATE alleles
			  SET allele_1='$allele1',allele_2='$allele2',
			  updated_on=NOW() 
			  WHERE genotyping_data_uid = $gen_uid";
	    }
	    $res = mysql_query($sql) or exitFatal($errFile, "Database Error: alleles processing - ". mysql_error() . ".\n\n$sql");
	    if ($res != 1) { 
                  $msg = "ERROR:  Allele not loaded! row = " . $rowNum ."\t" . $line;
                  fwrite($errFile, $msg);
                  $errLines++;
            }
 	} else {
 	    	$msg = "bad data at " . $line_name . " $data[$data_pt]\n";
                fwrite($errFile, $msg);
                $errLines++;
 	}
      }
    }
    $sql = "SET AUTOCOMMIT=1";
    $res = mysql_query($sql) or exitFatal($errFile, "Database Error: - ". mysql_error()."\n\n$sql");
} // End of while data 
fclose($reader);
echo "Genotyping record creation completed.\n";
echo "Start allele frequency calculation processing...\n";

// Do allele frequency calculations
$uniqExpID = array_unique($lineExpHash);

foreach ($uniqExpID AS $key=>$expID)  {

        if (empty($expID)) continue;

    // Step 1: get tht_base IDs for the experiment
    echo "Working on experiment id - " . $expID . "\n";
    $sql ="SELECT tht_base.tht_base_uid FROM tht_base WHERE tht_base.experiment_uid = $expID";
    $res = mysql_query($sql) or
        exitFatal($errFile, "Database Error: tht_base lookup with experiment uid - ". $expID . " ". mysql_error() . ".\n\n$sql");

    while ($row = mysql_fetch_array($res)) {
        $tht_base_uid[] = $row['tht_base_uid'];
    }

//    echo "Size of experiment look up in tht_base - ".  sizeof($tht_base_uid) ."\n";
    if (sizeof($tht_base_uid) == 0) continue;

    $tht_base_uids = implode(",",$tht_base_uid);
    // echo "\t tht_base_uids list - " . $tht_base_uids  . "\n";
    // Step 2: get distinct marker_uid's for these tht_base IDs
    $sql ="SELECT DISTINCT g.marker_uid FROM genotyping_data AS g WHERE g.tht_base_uid IN ($tht_base_uids)";
    $res = mysql_query($sql) or 
        exitFatal($errFile, "Database Error: genotyping_data lookup with experiment uid - ". $expID . " ". mysql_error(). ".\n\n$sql");
    while ($row = mysql_fetch_array($res)) {
        $mk_uid[] = $row['marker_uid'];
    }
    $mk_uids = array_unique($mk_uid);

    //$tstcnt = 0;
    $res = mysql_query("SHOW COLUMNS FROM allele_frequencies");
    while($row = mysql_fetch_object($res)){
        if(ereg(('set|enum'), $row->Type)) {
            eval(ereg_replace('set|enum', '$'.$row->Field.' = array', $row->Type).';');
        }
    }

    foreach ($mk_uids as $value) {

        if (empty($value)) continue;
        //get marker name
        $sql ="SELECT markers.marker_name FROM markers
                   WHERE marker_uid = $value";
        $res = mysql_query($sql) or exitFatal($errFile, "Database Error: marker name retrieval - ". mysql_error() . ".\n\n$sql");
        $rdata = mysql_fetch_assoc($res);
        $mname = $rdata['marker_name'];
        echo "-+- marker name ".$mname." for marker ".$value."\n";

        // get genotype IDs for a marker
        $sql ="SELECT g.genotyping_data_uid AS gid FROM genotyping_data AS g
                    WHERE g.tht_base_uid IN ($tht_base_uids) AND g.marker_uid = $value";
        $res = mysql_query($sql) or exitFatal($errFile, "Database Error: genotyping_data retrieval - ". mysql_error() . ".\n\n$sql");
        while ($row = mysql_fetch_array($res)) {
            $geno_uid[] = $row['gid'];
        }
        echo "--- num genotype ids ".count($geno_uid)." for marker ".$value."\n";
        $geno_uids = implode(",",$geno_uid);
        //print_r($geno_uids);
        if (strlen($geno_uids) == 0 ) echo "Oops, no Genotype_data_uid\n";

        // get alleles and gentrain score
        $sql ="SELECT a.allele_1,a.allele_2, a.GT_score FROM alleles AS a
                    WHERE a.genotyping_data_uid IN ($geno_uids)";
        $res = mysql_query($sql) or exitFatal($errFile, "Database Error: genotyping_data retrieval - ". mysql_error() . ".\n\n$sql");

        while ($row = mysql_fetch_array($res)) {
            $a1[]=$row['allele_1'];
            $a2[]=$row['allele_2'];
            if ($row['GT_score'] == "" ) {
                $gt[] = null;
            } else {
                $gt[] =$row['GT_score'];
            }
        }
        /* for ($i = 0; $i < count($a1); $i++) {
        echo $i." alleles ".$a1[$i].$a2[$i].$gt[$i]."\n";}*/

   // Loop through markers to get a count
        $aacnt = 0;
        $abcnt = 0;
        $bbcnt = 0;
        $misscnt =0;
        for ($i = 0; $i < count($a1); $i++) {
            if (($a1[$i] == 'A') and ($a2[$i] == 'A')) {
                $aacnt++;
            } elseif (($a1[$i] == 'B') and ($a2[$i] == 'B')) {
                $bbcnt++;
            } elseif ((($a1[$i] == 'A') and ($a2[$i] == 'B')) or (($a1[$i] == 'B') and ($a2[$i] == 'A'))) {
                $abcnt++;
            } elseif (($a1[$i] == '-') and ($a2[$i] == '-')) {
                $misscnt++;
            } else {
                exitFatal($errFile, $i." marker ".$value . " " . $a1[$i] . "not matching anything.");
            }
        }  //end for
        $total = $aacnt + $abcnt + $bbcnt + $misscnt;
        $aafreq = round($aacnt / $total,3);
        $bbfreq = round($bbcnt / $total,3);
        $abfreq = round($abcnt / $total,3);
        $maf = round(100 * min((2 * $aacnt + $abcnt) /$total, ($abcnt + 2 * $bbcnt) / $total),1);
        if (($aacnt == $total) or ($abcnt == $total) or ($bbcnt == $total)) {
            $mono = $monomorphic[0];//is monomorphic
        } else {
            $mono = $monomorphic[1];
        }

       //echo $mono." Miss: ".$misscnt." AA ".$aacnt." BB ".$bbcnt." AB ".$abcnt." MAF ".$maf." total ".$total."\n";
       //$tstcnt++;

        //if ($tstcnt > 1600) {
        //    exitFatal ($errFile, "Error: tstcnt > 1600");
        //}

        $result =mysql_query("SELECT allele_frequency_uid FROM allele_frequencies where experiment_uid = $expID and marker_uid = $value");
                $rgen=mysql_num_rows($result);
                if ($rgen < 1) {
                        $sql = "INSERT INTO allele_frequencies (marker_uid, experiment_uid, missing, aa_cnt, aa_freq, ab_cnt, ab_freq,
                bb_cnt, bb_freq, total, monomorphic, maf, gentrain_score, description,  updated_on, created_on)
                VALUES ($value, $expID, $misscnt, $aacnt, $aafreq, $abcnt, $abfreq, $bbcnt, $bbfreq, $total, '$mono',
                $maf, 0, '$mname', NOW(), NOW())";
        } else {
                        $sql = "UPDATE allele_frequencies
                                                SET missing = '$misscnt', aa_cnt = '$aacnt', aa_freq = $aafreq, ab_cnt = $abcnt, ab_freq = $abfreq, bb_cnt = $bbcnt,
                                                bb_freq = $bbfreq, total = $total, monomorphic = '$mono', maf= $maf,
                        description = '$mname', updated_on = NOW()
                                                WHERE experiment_uid = $expID and marker_uid = $value";
                }
        mysql_query($sql) or exitFatal($errFile, "Database Error: during update or insertion into  allele_frequencies table - ". mysql_error() . "\n\n$sql");
        //reset key variables
	unset($geno_uid);
        unset($a1);
        unset($a2);
        unset($gt);
    }
    unset ($mk_uid);
    unset ($mk_uids);
    unset ($tht_base_uid);
}
fclose($errFile);

echo "Allele frequency calculations completed.\n";

// Send out status email
if (filesize($errorFile)  > 0) {
    $body = "There was a problem during the offline importing process.\n".
        "Please have the curator review the error file at " . $urlPath.'curator_data/'.$tPath . "\n";
    echo "Genotype Data Import processing encountered some errors, check error file ". $errorFile , " for more information\n";
    
} else {
    $body = "The offline genotype data import completed successfully.\n".
			"Genotyping data import completed at - ". date("m/d/y : H:i:s", time()). "\n\n".
            "Additional information can be found at ".$urlPath.'curator_data/'.$tPath."genoProc.out\n";
    echo "Genotype Data Import Processing Successfully Completed\n";
}
mail($emailAddr, $subject, $body, $mailheader);

echo "Genotype Data Import Done\n";
echo "Finish time - ". date("m/d/y : H:i:s", time()). "\n";

$sql = "SELECT input_file_log_uid from input_file_log 
	WHERE file_name = '$filename'";
$res = mysql_query($sql) or die("Database Error: input_file lookup  - ". mysql_error() ."<br>".$sql);
$rdata = mysql_fetch_assoc($res);
$input_uid = $rdata['input_file_log_uid'];
        
if (empty($input_uid)) {
	$sql = "INSERT INTO input_file_log (file_name,users_name, created_on)
		VALUES('$filename', '$userName', NOW())";
} else {
	$sql = "UPDATE input_file_log SET users_name = '$userName', created_on = NOW()
		WHERE input_file_log_uid = '$input_uid'"; 
}
mysql_query($sql) or die("Database Error: Input file log entry creation failed - " . mysql_error() . "\n\n$sql");

$filename = stristr($lineTransFile,basename($lineTransFile));
$sql = "SELECT input_file_log_uid from input_file_log 
        WHERE file_name = '$filename'";
$res = mysql_query($sql) or die("Database Error: input_file lookup  - ". mysql_error() ."<br>".$sql);
$rdata = mysql_fetch_assoc($res);
$input_uid = $rdata['input_file_log_uid'];

if (empty($input_uid)) {
        $sql = "INSERT INTO input_file_log (file_name,users_name, created_on)
                VALUES('$filename', '$userName', NOW())";
} else {
        $sql = "UPDATE input_file_log SET users_name = '$userName', created_on = NOW()
                WHERE input_file_log_uid = '$input_uid'";
}
mysql_query($sql) or die("Database Error: Input file log entry creation failed - " . mysql_error() . "\n\n$sql");

exit(0);

//********************************************************
function exitFatal ($handle, $msg) {

    global $emailAddr;
    global $mailheader;
    global $tPath; 
	global $urlPath; 
    
    // Send to stdout
    echo $msg;
    // send to error log
    fwrite($handle, $msg);
    fclose($handle);
    // Send email
    $subject = 'Fatal Import Error';
    $body = "There was a fatal problem during the offline importing process.\n". $msg. "\n\n" .
        "Additional information can be found at ".$urlPath.'curator_data/'.$tPath. "\n";      
    mail($emailAddr, $subject, $body, $mailheader);
    exit(1);
}

?>

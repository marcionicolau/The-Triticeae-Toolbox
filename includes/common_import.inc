<?php

/* This file contains functions that are used in multiple data import files
  
  Function Listing:
    1. find: does a wildcard search on an array
    2. error: writes out error information
    3. get_lineuid: checks if a line is in the database, checks both line record and synonym table,
        it also checks for the case with spaces removed and the case where spaces have been replaced by underscores
        
*/
   /**
     * Takes a needle and haystack (just like in_array()) and does a wildcard search on it's values.
     *
     * @param    string        $string        Needle to find
     * @param    array        $array        Haystack to look through
     * @result    array                    Returns the elements that the $string was found in
     */
    function find ($string, $array = array ())
    {       
        foreach ($array as $key => $value) {
            unset ($array[$key]);
            if (strpos($value, $string) !== false) {
                $array[$key] = $key;
            }
        }       
        return $array;
    }
    
    /*
 * Types:
 *	Warning - 0
 *	Fatal - 1
 *
 * This function displays errors encountered in the program. It handles them and displays them in a pretty format
 * 
 */
function error($type, $text) {

	switch($type) {

	case 0:
		echo "<p class=\"warning\"><strong>Warning:</strong> $text</p>\n";
		break;

	case 1:
		echo "<p class=\"error\"><strong>Error:</strong> $text</p>\n";
		break;

	}

}


    /*
 * If no line found, function returns FALSE
 *	If a line is found, the result is the line_record_uid
 *
 * get_lineuid: checks if a line is in the database, checks both line record and synonym table,
 *       it also checks for the case with spaces removed and the case where spaces have been replaced by underscores
 * 
 */

	function get_lineuid ($line) {
               // find line name list and group it into th proper experiment
                // If the name does not work, also check versions with spaces removed
                // and spaces replaced by underscores
                $line_nosp = str_replace(" ","",$line);
                $line_us = str_replace("_","",$line);
                $line_hyp = str_replace("-","",$line);
                $line_sql = mysql_query("SELECT line_record_uid AS lruid
                    FROM line_records
                    WHERE line_record_name = '$line'
                        OR line_record_name = '$line_nosp'
                        OR line_record_name = '$line_us'
                        OR line_record_name = '$line_hyp'
					");

                
                if (mysql_num_rows($line_sql)<1)  {
                    //echo "Line ".$line." ".$line_us.$line_hyp.$line_nosp.$line_sql." \n";
                    $line_sql = mysql_query("SELECT line_record_uid AS lruid
                        FROM line_synonyms
                        WHERE line_synonym_name = '$line'
                            OR line_synonym_name = '$line_nosp'
                            OR line_synonym_name = '$line_us'
                            OR line_synonym_name = '$line_hyp'");
                    
                    if (mysql_num_rows($line_sql)<1) {
                        //echo "Line ".$line." is not in the line record or synonym table\n";
                        return FALSE;
                    }
                }


                while ($row = mysql_fetch_array($line_sql, MYSQL_ASSOC)) {
                    $result[] = $row["lruid"];  
                }
            return $result;


}

?>
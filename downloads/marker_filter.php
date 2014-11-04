<?php
/**
 * Library used for marker and line filtering
 *
 * PHP version 5.3
 *
 * @author   Clay Birkett <clb343@cornell.edu>
 * @license  http://triticeaetoolbox.org/wheat/docs/LICENSE Berkeley-based
 * @link     http://triticeaetoolbox.org/wheat/downloads/marker_filter.php
 */

/**
 * calculate allele frequencies using allele_frequencies table
 *
 * @param array $lines         selected lines
 * @param float $min_maf       minimum marker allele frequence
 * @param float $max_missing   maximum missing markers
 * @param float $max_miss_line maximum missing lines
 *
 * @return $markers_filtered
 */
function calculate_db($lines, $min_maf, $max_missing, $max_miss_line)
{
    global $mysqli;
    $tmp = count($lines);
    if ($tmp == 0) {
        return;
    }
    $selectedlines = implode(",", $lines);

    if (isset($_SESSION['geno_exps'])) {
        $count = $_SESSION['geno_exps_cnt'];
        return $count;
        $experiment_uid = $_SESSION['geno_exps'];
        $experiment_uid = $experiment_uid[0];
        $sql = "SELECT marker_uid, maf, missing, total from allele_frequencies where experiment_uid = $experiment_uid";
        $res = mysql_query($sql) or die(mysql_error() . $sql);
        while ($row = mysql_fetch_row($res)) {
            $marker_uid = $row[0];
            $maf = $row[1];
            $miss = $row[2];
            $total = $row[3];
            $miss_per = 100 * ($miss / $total);
            if (($miss_per > $max_missing) or ($maf < $min_maf)) {
            } else {
                $markers_filtered[$marker_uid] = 1;
            }
        }
    } else {
        //get genotype experiments that correspond with the Datasets (BP and year)
        //selected for the experiments
        $sql_exp = "SELECT DISTINCT e.experiment_uid AS exp_uid
        FROM experiments e, experiment_types as et, line_records as lr, tht_base as tb
        WHERE e.experiment_type_uid = et.experiment_type_uid
        AND lr.line_record_uid = tb.line_record_uid
        AND e.experiment_uid = tb.experiment_uid
        AND lr.line_record_uid in ($selectedlines)
        AND et.experiment_type_name = 'genotype'";
        $res = mysqli_query($mysqli, $sql_exp) or die(mysqli_error($mysqli) . "<br>" . $sql_exp);
        if (mysqli_num_rows($res)>0) {
            while ($row = mysqli_fetch_array($res)) {
                $exp[] = $row["exp_uid"];
            }
            $exp = implode(',', $exp);
        }

        $sql_mstat = "SELECT af.marker_uid as marker, SUM(af.aa_cnt) as sumaa,
         SUM(af.missing)as summis, SUM(af.bb_cnt) as sumbb,
         SUM(af.total) as total, SUM(af.ab_cnt) AS sumab
         FROM allele_frequencies AS af
         WHERE af.experiment_uid in ($exp)
         group by af.marker_uid";

        $res = mysqli_query($mysqli, $sql_mstat) or die(mysqli_error($mysqli));
        $num_mark = mysqli_num_rows($res);
        $num_maf = $num_miss = $num_removed = 0;

        while ($row = mysqli_fetch_array($res)) {
            $marker_uid = $row["marker"];
            $maf1 = (2*$row["sumaa"]+$row["sumab"])/(2*$row["total"]);
            $maf2 = ($row["sumab"]+2*$row["sumbb"])/(2*$row["total"]);
            $maf = round(100*min($maf1, $maf2), 1);
            $miss = round(100*$row["summis"]/$row["total"], 1);
            if (($miss > $max_missing) or ($maf < $min_maf)) {
            } else {
                $markers_filtered[] = $marker_uid;
            }
        }
    }
    $count = count($markers_filtered);
    return $count;
}

    /**
     * calculate allele frequence and missing data using selected lines
     *
     * @param array  $lines         selected lines
     * @param floats $min_maf       minimum marker allele frequency
     * @param floats $max_missing   maximum missing markers
     * @param floats $max_miss_line maximum missing lines
     *
     * @return $markers_filtered, $lines_filtered
    */
function calculate_af($lines, $min_maf, $max_missing, $max_miss_line)
{
    global $mysqli;
    if (isset($_SESSION['clicked_buttons'])) {
        $tmp = count($_SESSION['clicked_buttons']);
        $saved_session = $saved_session . ", $tmp markers";
        $markers = $_SESSION['clicked_buttons'];
        $marker_str = implode(',', $markers);
    } else {
        $markers_filtered = array();
        $markers = array();
        $marker_str = "";
    }

    //create list of selected markers
    foreach ($markers as $key => $marker_uid) {
        $selected_markers[$marker_uid] = 1;
        //echo "selected $marker_uid\n";
    }

    //get location information for markers
    $sql = "select marker_uid, marker_name from allele_byline_idx order by marker_uid";
    $res = mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli) . "<br>" . $sql);
    $i=0;
    while ($row = mysqli_fetch_array($res)) {
        $uid = $row[0];
        $marker_list[$i] = $row[0];
        $marker_list_name[$i] = $row[1];
        $marker_list_loc[$uid] = $i;
        $i++;
    }

    //get location information for lines
    $sql = "select line_record_uid, line_record_name from line_records";
    $res = mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli) . "<br>" . $sql);
    while ($row = mysqli_fetch_array($res)) {
        $uid = $row[0];
        $line_list_name[$uid] = $row[1];
    }
   
    //calculate allele frequence and missing
    $marker_misscnt = array();
    foreach ($lines as $line_record_uid) {
        $sql = "select alleles from allele_byline where line_record_uid = $line_record_uid";
        $res = mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli) . "<br>" . $sql);
        if ($row = mysqli_fetch_array($res)) {
            $alleles = $row[0];
            $outarray = explode(',', $alleles);
            $i=0;
            foreach ($outarray as $allele) {
                if ($allele=='AA') {
                    $marker_aacnt[$i]++;
                } elseif (($allele=='AB') or ($allele=='BA')) {
                    $marker_abcnt[$i]++;
                } elseif ($allele=='BB') {
                    $marker_bbcnt[$i]++;
                //need to check for both conditions otherwise the output will include markers with missing data
                } elseif (($allele=='--') || ($allele=='')) {
                    $marker_misscnt[$i]++;
                } else {
                    echo "illegal genotype value $allele for marker $marker_list_name[$i]<br>";
                }
                $i++;
            }
        } else {
            foreach ($marker_list as $i => $value) {
                $marker_misscnt[$i]++;
            }
        }
    }
    $num_mark = 0;
    $num_maf = $num_miss = $num_removed = 0;
    foreach ($marker_list as $i => $marker_uid) {
        //if there are selected markers then only calculate allele frequencies for these
        if (isset($_SESSION['clicked_buttons']) && !isset($selected_markers[$marker_uid])) {
            continue;
        }
        $total_af = $marker_aacnt[$i] + $marker_abcnt[$i] + $marker_bbcnt[$i];
        $total = $total_af + $marker_misscnt[$i];
        if ($total_af > 0) {
            $maf = 100 * min((2 * $marker_aacnt[$i] + $marker_abcnt[$i]) /$total_af, ($marker_abcnt[$i] + 2 * $marker_bbcnt[$i]) / $total_af);
            $miss = 100 * $marker_misscnt[$i]/$total;
            if ($maf < $min_maf) {
                $num_maf++;
            }
            if ($miss > $max_missing) {
                $num_miss++;
            }
            if (($miss > $max_missing) or ($maf < $min_maf)) {
                $num_removed++;
            } else {
                $markers_filtered[] = $marker_uid;
            }
            $num_mark++;
        }
    }
    //echo "<br>num of markers with data = $num_mark<br>\n";
    $_SESSION['filtered_markers'] = $markers_filtered;
    $count = count($markers_filtered);
    if ($count == 0) {
          //if none of markers meet maf requirements then we can not filter lines by missing data
          $lines_filtered = $lines;
    } else {
        //calculate missing from each line
        foreach ($lines as $line_record_uid) {
            $sql = "select alleles from allele_byline where line_record_uid = $line_record_uid";
            $res = mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli) . "<br>" . $sql);
            if ($row = mysqli_fetch_array($res)) {
                $alleles = $row[0];
                $outarray = explode(',', $alleles);
                $line_misscnt[$line_record_uid] = 0;
                foreach ($markers_filtered as $marker_uid) {
                    $loc = $marker_list_loc[$marker_uid];
                    $allele = $outarray[$loc];
                    if ($allele=='--') {
                        $line_misscnt[$line_record_uid]++;
                    }
                }
            } else {
                $line_misscnt[$line_record_uid] = $count;
            }
        }
        $lines_removed = 0;
        $lines_removed_name = "";
        $num_line = 0;
        foreach ($lines as $line_record_uid) {
            $miss = 100*$line_misscnt[$line_record_uid]/$count;
            if ($miss > $max_miss_line) {
                $lines_removed++;
                if ($lines_removed_name == "") {
                    $lines_removed_name = $line_list_name[$line_record_uid];
                } else {
                    $lines_removed_name = $lines_removed_name . ", $line_list_name[$line_record_uid]";
                }
            } else {
                $lines_filtered[] = $line_record_uid;
            }
            $num_line++;
        }
    }
    $_SESSION['filtered_lines'] = $lines_filtered;
    if (strlen($lines_removed_name) > 75) {
         $comm = substr($lines_removed_name, 0, 75) . " ...";
    } else {
         $comm = $lines_removed_name;
    }
    $count2 = count($lines_filtered);

    ?>
    <table>
    <tr><td><a onclick="filterDesc( <?php echo ($min_maf) ?>, <?php echo ($max_miss_line) ?>, <?php echo ($max_miss_line) ?>)">Removed by filtering</a><td>Remaining
    <tr><td><?php echo ($num_maf) ?><i> markers have a minor allele frequency (MAF) less than </i><b><?php echo ($min_maf) ?></b><i>%
    <br><?php echo ($num_miss) ?><i> markers are missing more than </i><b><?php echo ($max_missing) ?></b><i>% of data
    <br><b><?php echo ($num_removed) ?></b><i> markers removed</i>
    <td><b><?php echo ("$count") ?></b><i> markers</i>
    <tr><td><?php
    if ($lines_removed == 1) {
        echo ("</i><b>$lines_removed") ?></b><i> line is missing more than </i><b><?php echo ($max_miss_line) ?></b><i>% of data</b></i>
        <?php
    } else {
        echo ("</i><b>$lines_removed") ?></b><i> lines are missing more than </i><b><?php echo ($max_miss_line) ?></b><i>% of data </b></i>
        <?php
    }
    if ($lines_removed_name != "") {
        ?>
        <br>(<a onclick="linesRemoved('<?php echo ($lines_removed_name) ?>')"><?php echo ($comm) ?></a>)
        <?php
    }
    echo "<td><b>$count2</b><i> lines</a>";
    echo ("</table>");
}

    /**
     * calculate allele frequence and missing data using selected lines and allele_frequencies table
     *
     * @param array  $lines         selected lines
     * @param floats $min_maf       minimum marker allele frequency
     * @param floats $max_missing   maximum missing markers
     * @param floats $max_miss_line maximum missing lines
     *
     * @return $markers_filtered, $lines_filtered
    */
function calculate_afe($lines, $min_maf, $max_missing, $max_miss_line)
{
    global $mysqli;
    if (isset($_SESSION['geno_exps'])) {
        $experiment_uid = $_SESSION['geno_exps'];
        $experiment_uid = $experiment_uid[0];
    } else {
        echo "Error: should select genotype experiment befor download\n";
    }

    $num_maf = 0;
    $num_miss = 0;
    $num_mark = 0;
    $num_removed = 0;
    $sql = "SELECT marker_uid, maf, missing, total from allele_frequencies where experiment_uid = $experiment_uid";
    $res = mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli) . $sql);
    while ($row = mysqli_fetch_row($res)) {
        $marker_uid = $row[0];
        $maf = $row[1];
        $miss = $row[2];
        $total = $row[3];
        $miss_per = 100 * ($miss / $total);
        if ($maf < $min_maf) {
            $num_maf++;
        }
        if ($miss_per > $max_missing) {
            $num_miss++;
        }
        if (($miss_per > $max_missing) or ($maf < $min_maf)) {
            $num_removed++;
        } else {
            $markers_filtered[$marker_uid] = 1;
        }
        $num_mark++;
    }
    $count = count($markers_filtered);
    ?>
    <table>
    <tr><td><a onclick="filterDesc( <?php echo ($min_maf) ?>, <?php echo ($max_miss_line) ?>, <?php echo ($max_miss_line) ?>)">Removed by filtering</a><td>Remaining
    <tr><td><?php echo ($num_maf) ?><i> markers have a minor allele frequency (MAF) less than </i><b><?php echo ($min_maf) ?></b><i>%
    <br><?php echo ($num_miss) ?><i> markers are missing more than </i><b><?php echo ($max_missing) ?></b><i>% of data
    <br><b><?php echo ($num_removed) ?></b><i> markers removed</i>
    <td><b><?php echo ("$count") ?></b><i> markers</i>
    <?php
    echo ("</table>");
}

    /**
     * build genotype data files for tassel and rrBLUP using genotype experiment
     *
     * @param unknown_type $lines   lines
     * @param unknown_type $markers markers
     * @param integer      $dtype   file format
     * @param file         $h       file handle
     *
     * @return null
     */
    function type4BuildMarkersDownload($geno_exp, $min_maf, $max_missing, $dtype,$h)
    {
        $output = '';
        $outputheader = '';
        $delimiter ="\t";

        if (isset($_SESSION['selected_map'])) {
            $selected_map = $_SESSION['selected_map'];
        } else {
           $selected_map = "";
        }

        $sql = "SELECT marker_uid, maf, missing,total from allele_frequencies where experiment_uid = $geno_exp";
        $res = mysql_query($sql) or die(mysql_error() . $sql);
        while ($row = mysql_fetch_row($res)) {
            $marker_uid = $row[0];
            $maf = $row[1];
            $miss = $row[2];
            $total = $row[3];
            $miss_per = 100 * ($miss / $total);
            if (($miss_per > $max_missing) OR ($maf < $min_maf)) {
            } else {
                $markers_filtered[] = $marker_uid;
                $marker_lookup[$marker_uid] = 1;
            }
            $num_mark++;
        }
        $markers_str = implode(",", $markers_filtered);

        //order the markers by map location
        //tassel v5 needs markers sorted when position is not unique
        if ($selected_map == "") {
            $marker_list_mapped = array();
            $marker_list_chr = array();
         } else {
             $sql = "select markers.marker_uid, CAST(1000*mim.start_position as UNSIGNED), mim.chromosome from markers, markers_in_maps as mim, map, mapset
             where markers.marker_uid IN ($markers_str)
             AND mim.marker_uid = markers.marker_uid
             AND mim.map_uid = map.map_uid
             AND map.mapset_uid = mapset.mapset_uid
             AND mapset.mapset_uid = $selected_map 
             order by mim.chromosome, CAST(1000*mim.start_position as UNSIGNED), BINARY markers.marker_name";
             $res = mysql_query($sql) or die(mysql_error() . "<br>" . $sql);
             while ($row = mysql_fetch_array($res)) {
               $marker_uid = $row[0];
               $pos = $row[1];
               $chr = $row[2];
               $marker_list_mapped[$marker_uid] = $pos;
               $marker_list_chr[$marker_uid] = $chr;
             }
         }

        //generate an array of selected markers and add map position if available
        $sql = "select marker_uid, marker_name, A_allele, B_allele, marker_type_name from markers, marker_types
        where marker_uid IN ($markers_str)
        AND markers.marker_type_uid = marker_types.marker_type_uid
        order by BINARY marker_name";
        $res = mysql_query($sql) or die(mysql_error() . "<br>" . $sql);
        while ($row = mysql_fetch_array($res)) {
            $marker_uid = $row[0];
            $marker_name = $row[1];
            if (preg_match("/[A-Z]/", $row[2]) && preg_match("/[A-Z]/", $row[3])) {
                $allele = $row[2] . "/" . $row[3];
            } elseif (preg_match("/DArT/", $row[4])) {
                $allele = $row[2] . "/" . $row[3];
            } else {
                $allele = "N/N";
            }
            $marker_list_name[$marker_uid] = $marker_name;
            $marker_list_allele[$marker_uid] = $allele;
            $marker_list_type[$marker_uid] = $row[4];
        }

        //get header, tassel requires all fields even if they are empty
        if ($dtype == "qtlminer") {
            $outputheader = "rs\talleles\tchrom\tpos";
        } else {
            $outputheader = "rs#\talleles\tchrom\tpos\tstrand\tassembly#\tcenter\tprotLSID\tassayLSID\tpanelLSID\tQCcode";
        }
        $sql = "select line_name_index from allele_bymarker_expidx where experiment_uid = $geno_exp";
        $res = mysql_query($sql) or die(mysql_error() . "<br>" . $sql);
        if ($row = mysql_fetch_array($res)) {
            $name = $row[0];
            $outputheader .= "\t$name";
            if (isset($unique[$name])) {
                echo "duplicate name $name<br>\n";
            } else {
                $unique[$name] = 1;
            }
        } else {
            die("<font color=red>Error - genotype experiment should be selected before download</font>");
        }
        $nelem = count($line_names);
        $outputheader = preg_replace("/,/", "\t", $outputheader);
        $outputheader = str_replace(" ", "", $outputheader);
        fwrite($h, "$outputheader\n");

        $pos_index = 0;
        if ($dtype == "qtlminer") {
            $sql = "select marker_uid, marker_name, chrom, pos, alleles from allele_bymarker_exp_101 where experiment_uid = $geno_exp order by BINARY chrom, pos, BINARY marker_name";
        } else {
            $sql = "select marker_uid, marker_name, chrom, pos, alleles from allele_bymarker_exp_ACTG where experiment_uid = $geno_exp order by BINARY chrom, pos";
        }
        $res = mysql_query($sql) or die(mysql_error() . "<br>" . $sql);
        while ($row = mysql_fetch_array($res)) {
            $marker_id = $row[0];
            $marker_name = $row[1];
            $chrom = $row[2];
            $pos = $row[3];
            $alleles = $row[4];
            $allele = $marker_list_allele[$marker_id];
            $marker_type = $marker_list_type[$marker_id];
            if (empty($chrom)) {
                if (isset($marker_list_mapped[$marker_id])) {
                    $chrom = $marker_list_chr[$marker_id];
                    $pos = $marker_list_mapped[$marker_id];
                }
            }
            if (isset($marker_lookup[$marker_id])) {
                if (empty($chrom)) {
                    $chrom = 'UNK';
                    $pos = $pos_index;
                    $pos_index += 10;
                }
                if ($dtype == "qtlminer") {
                    fwrite($h, "$marker_name\t$allele\t$chrom\t$pos");
                } else {
                    fwrite($h, "$marker_name\t$allele\t$chrom\t$pos\t\t\t\t\t\t\t");
                }
                $alleles = preg_replace("/,/", "\t", $alleles);
                fwrite($h, "\t$alleles\n");
            }
        }
        $count = count($unique);
    }


<?php
/**
 * Canopy Spectral Reflectance
*
* PHP version 5.3
* Prototype version 1.5.0
*
* @category PHP
* @package  T3
* @author   Clay Birkett <clb343@cornell.edu>
* @license  http://triticeaetoolbox.org/wheat/docs/LICENSE Berkeley-based
* @version  GIT: 2
* @link     http://triticeaetoolbox.org/wheat/curator_data/cal_index.php
*
*/

require 'config.php';
/*
 * Logged in page initialization
 */
include($config['root_dir'] . 'includes/bootstrap_curator.inc');

connect();
$mysqli = connecti();
loginTest();

/* ******************************* */
$row = loadUser($_SESSION['username']);

////////////////////////////////////////////////////////////////////////////////
ob_start();

authenticate_redirect(array(USER_TYPE_ADMINISTRATOR, USER_TYPE_CURATOR));
ob_end_flush();


new Experiments($_GET['function']);

/** CSR phenotype experiment
 * 
 * @author claybirkett
 *
 */

class Experiments
{
	
	/**
	 * Using the class's constructor to decide which action to perform
	 * @param string $function action to perform
	 */
	public function __construct($function = null)
	{	
		switch($function)
		{
                    case 'display':
                                $this->typeDisplay();
                                break;							
					
		    default:
				$this->typeExperiments(); /* intial case*/
				break;
			
		}	
	}

/**
 * display the file that has been loaded
 */
private function typeDisplay() {
  global $config;
  include($config['root_dir'] . 'theme/admin_header.php');
  if (isset($_GET['uid'])) {
    $experiment_uid = $_GET['uid'];
  } else {
    die("Error - no experiment found<br>\n");
  }
  $sql = "select trial_code from experiments where experiment_uid = $experiment_uid";
  $res = mysql_query($sql) or die (mysql_error());
  if ($row = mysql_fetch_assoc($res)) {
    $trial_code = $row["trial_code"];
  } else {
    die("Error - invalid uid $uid<br>\n");
  }

  //get line names
  $sql = "select line_record_uid, line_record_name from line_records";
  $res = mysql_query($sql) or die (mysql_error());
  while ($row = mysql_fetch_assoc($res)) {
    $uid = $row["line_record_uid"];
    $line_name = $row["line_record_name"];
    $line_list[$uid] = $line_name;
  } 

  $count = 0;
  $sql = "select * from fieldbook order by plot";
  $res = mysql_query($sql) or die (mysql_error());
  echo "<h2>Field Book for $trial_code</h2>\n";
  echo "<table>";
  echo "<tr><td>plot<td>line_name<td>row<td>column<td>entry<td>replication<td>block<td>subblock<td>treatment<td>block_tmt<td>subblock_tmt<td>check<td>Field_ID<td>note";
  while ($row = mysql_fetch_assoc($res)) {
    $expr = $row["experiment_uid"];
    $range = $row["range_id"];
    $plot = $row["plot"];
    $entry = $row["entry"];
    $line_uid = $row["line_uid"];
    $field_id = $row["field_id"];
    $note = $row["note"];
    $rep = $row["replication"];
    $block = $row["block"];
    $subblock = $row["subblock"];
    $row_id = $row["row_id"];
    $col_id = $row["column_id"];
    $treatment = $row["treatment"];
    $main_plot_tmt = $row["block_tmt"];
    $subblock_tmt = $row["subblock_tmt"];
    $check = $row["check_id"];
    echo "<tr><td>$plot<td>$line_list[$line_uid]<td>$row_id<td>$col_id<td>$entry<td>$rep<td>$block<td>$subblock<td>$treatment<td>$main_plot_tmt<td>$subblock_tmt<td>$check<td>$field_id<td>$note\n";
    $count++;
  }
  echo "</table>";
}

/**
 * wrapper to display header and footer for the input form
 */
private function typeExperiments()
	{
		global $config;
                global $mysqli;
		include($config['root_dir'] . 'theme/admin_header.php');

		echo "<h2>Calculate CSR Index</h2>"; 
		
			
		$this->type_Experiment_Name();

		$footer_div = 1;
        include($config['root_dir'].'theme/footer.php');
	}
	
	/**
	 * display input form
	 */
	private function type_Experiment_Name()
	{
            global $config;
            global $mysqli;
	?>

<style type="text/css">
  th {background: #5B53A6 !important; color: white !important; border-left: 2px solid #5B53A6}
  table {background: none; border-collapse: collapse}
  td {border: 0px solid #eee !important;}
  h3 {border-left: 4px solid #5B53A6; padding-left: .5em;}
</style>
<script type="text/javascript" src="curator_data/csr.js"></script>

<!-- <p><strong>Note: </strong><font size="2px">Please load the corresponding
    <a href="<?php echo $config['base_url'] ?>curator_data/input_annotations_upload_excel.php">Phenotype 
      Experiment Annotations</a> file before uploading the results files. </font></p> -->

<form action="curator_data/cal_index_check.php" enctype="multipart/form-data">
  <table>
  <tr><td><strong>Trial Name:</strong><td>
  <select id="trial" name="trial" onchange="javascript: update_trial()">
<?php
/*echo "<option value=''>select a trial</option>\n";*/
$sql = "select trial_code, experiments.experiment_uid, measurement_uid from experiments, csr_measurement where experiments.experiment_uid = csr_measurement.experiment_uid"; 
$res = mysqli_query($mysqli,$sql) or die(mysqli_error($mysqli) . "<br>$sql");
echo "<option>select a trial</option>\n";
while ($row = mysqli_fetch_assoc($res)) {
  $tc = $row['trial_code'];
  $mid = $row['measurement_uid'];
  $trial_list[$uid] = $tc;
  echo "<option value=\"$mid\">$tc $mid</option>\n";
}
?>
</select>
  
  <tr><td><strong>Box Smoothing:</strong><td>
  <select id="smooth" name="smooth" onchange="javascript: update_smooth()">
  <option value='0'>1 points</option>
  <option value='3'>3 points</option>
  <option value='5'>5 points</option>
  </select>
  <tr><td><b>Wavelength<br>Parameters:</b>
      <td><input type="text" id="W1" name="W1" onchange="javascript: update_w1()">W1
      <td><input type="text" id="W2" name="W2" onchange="javascript: update_w2()">W2
  <tr><td><strong>Formula:</strong><td>
  <select id="formula1" name="formula1" onchange="javascript: update_f1()">
  <option value=''>Select a formula</option>
  <option value='W1 / W2'>W1 / W2</option>
  <option value='(W1 - W2)/(W1 + W2)'>(W1 - W2)/(W1 + W2)</option>
  </select>
  or
  <td><input type="text" id="formula2" name="formula2" size="50" onchange="javascript: update_f2()">Enter custom formula<br>
  </table>
  <p><input type="button" value="Calculate" onclick="javascript:cal_index()"/></p>
</form>
Typical wavelength parameters are: 450 (Blue), 680 (Red), 800 (NIR)<br>

<!--a href=login/edit_csr_field.php>Edit Field Book Table</a><br-->
<div id="step2">	
</div>	
<?php
	} /* end of type_Experiment_Name function*/
} /* end of class */

?>

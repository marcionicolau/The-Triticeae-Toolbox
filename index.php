<?php
require 'config.php';
include($config['root_dir'].'includes/bootstrap.inc');
include($config['root_dir'].'theme/normal_header.php');
connect();
?>

<h2>Home: The Breeders Funnyfarm</h2>

<p>This version of the Breeders Datafarm (BD) is for hands-on learning,
practice, and data experiments.

<p>Anyone can load data here as long as they
have <a href="http://malt.pw.usda.gov/t3/bdfunny/login.php">registered</a>,
and anyone can register. Note that you must register again here even if
you've done it already on the main BD website.

<p>All data loaded here will be <font color=red><b>gone</b></font>
tomorrow, unless loaded into the production BD. This database is copied
from
<a href="http://malt.pw.usda.gov/t3/bd/">http://malt.pw.usda.gov/t3/bd/</a>
 nightly. So once your data is ready please submit it for loading there.
<br><br>
    <div class="section">
      <p>If you knew everything about the genome of each of your
	breeding lines, wouldn't it be <b>fun</b> to decide who to cross?
	
      <h3>Welcome to the Breeders Datafarm!</h3>

      <p>BD is the web portal for data from US Uniform Regional
	Nurseries.  Supported by the <a href="http://scabusa.org">US
	Wheat and Barley Scab Initiative</a>, it is derived from The
	Triticeae Toolbox (T3), the database of
	the <a href="http://triticeaecap.org/">Triticeae Coordinated
	Agricultural Project</a> (T-CAP).

  <p><b>Participants</b>: The templates and instructions for data submission 
  are <a href="curator_data/instructions.php">here</a>.
  If your data are not totally public, please check the 
  <a href="toronto.php">Data Usage Policy</a>.
    <br>
    <input type="Button" value="Data submission" onclick="window.open('curator_data/instructions.php','_self')">

  </div>
		
  <p>
  <table cellpadding="0" cellspacing="0"><tbody>
  <tr>
  <th>Browse phenotype experiments</th>
  <th>&nbsp;</th>
  </tr>
	
  <tr><td>
  <select onchange="window.open('<?php echo $config['base_url']; ?>search_bp.php?table=CAPdata_programs&uid='+this.options[this.selectedIndex].value,'_top')">
  <!-- <option value='' disabled>Search by Breeding Program</option> -->
  <option selected value=''>Search by Breeding Program</option>
   <?php
  // dem jan13: Only include programs that have phenotype experiment trials.
  $sql = "select distinct
     data_program_name, data_program_code, cp.CAPdata_programs_uid as uid
     FROM CAPdata_programs cp, experiments e
     WHERE program_type = 'breeding'
     AND cp.CAPdata_programs_uid = e.CAPdata_programs_uid
     order by data_program_name asc;";
$r = mysql_query($sql) or die("<pre>" . mysql_error() . "\n$sql");
while($row = mysql_fetch_assoc($r)) {
  $progname = $row['data_program_name']." - ".$row['data_program_code'];
  $uid = $row['uid'];
  echo "<option value='$uid'>$progname</option>\n";
 }
?>
  </select></td>
  <td>Experiments whose entries include the program&apos;s lines</td>
  </tr>

  <tr><td>
  <select onchange="window.open('<?php echo $config['base_url']; ?>search_phenotype.php?table=experiments&pheno_name='+this.options[this.selectedIndex].value,'_top')">
  <option selected value="">Search by Trait</option>
  <?php
  $sql = "select distinct phenotypes_name from phenotypes
  order by phenotypes_name";
$r = mysql_query($sql) or die("<pre>" . mysql_error() . "\n$sql");
while($row = mysql_fetch_assoc($r)) {
  $pheno_name = $row['phenotypes_name'];
  echo "<option value='$pheno_name'>$pheno_name</option>\n";
 }
?>
</select></td>
<td></tr>

  <tr><td>
  <select onchange="window.open('<?php echo $config['base_url']; ?>view_search_yr2.php?table=experiments&year='+this.options[this.selectedIndex].value,'_top')">
  <option selected value=''>Search by Year</option>
  <?php
  $sql = "select distinct experiment_year from experiments
  order by experiment_year desc";
$r = mysql_query($sql) or die("<pre>" . mysql_error() . "\n$sql");
while($row = mysql_fetch_assoc($r)) {
  $year = $row['experiment_year'];
  echo "<option value='$year'>$year</option>\n";
 }
?>
</select></td>
<td>Year of harvest</tr>

</tbody></table>

<?php 
  $footer_div=1;
include($config['root_dir'].'theme/footer.php'); ?>

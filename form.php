<form id="regex_get" > 
   	Field: <input type="text" name="field" /> 
	Regex: <input type="text" name="regex" />
   	number of results: <input type="text" name="amount" />
	<input type="hidden" name="action" value="filter_logs" /> 
    <input type="submit" name="submit"  />
</form>

<script type="text/javascript">
   var $j = jQuery.noConflict();
   $j("#regex_get").submit(function() {
	var dataset = $j("#regex_get").serialize();
	$j.ajax({
	  type: "POST", url: <?php echo "\"" . get_settings('siteurl') ."/wp-admin/admin-ajax.php\""; ?>,
		data: dataset, dataType: "json",
		success: function(dataset) {
			$j("#replace").html(dataset.html);
		}
	});
	return false;
   });
</script>

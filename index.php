<?php
require_once(__DIR__ . '/../../config.php');
require_once("$CFG->libdir/adminlib.php");
require_once("$CFG->libdir/formslib.php");

require_once(__DIR__ . '/classes/form.php');

$context = context_system::instance();
require_login();
$PAGE->set_context($context);
$PAGE->set_url('/local/lsucli/index.php');
$PAGE->requires->css('/local/lsucli/styles.css');

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('lsucli', 'local_lsucli'));

$mform = new lsucli_form();

$data = $mform->get_data();
if ($data !== null) {
    $command = $mform->build_cmd();
    exec($command, $output, $return_code);
    $return_class = $return_code == 0 ? 'alert-success' : 'alert-danger';
    echo "<div class='alert $return_class'><strong>";
    echo $return_code == 0 ? "Command executed successfully!" : "Command execution failed!"; 
    echo "</strong><br />$command</div>" .
        "<pre class='output'>" .
        implode("\n", $output) .
        "</pre>";
    $mform->reset();
} else if (!$mform->is_submitted()) {
} else if (!$mform->is_validated()) {
    echo "<div class='alert alert-danger'>There were errors in your form submission. Please correct them and try again.</div>";
}

$mform->display();
echo $OUTPUT->footer();
?>
<script>
    // Moodle themes don't always like setting titles on labels properly.
    document.querySelectorAll('input').forEach((e, i) => {
        var label = e.closest('label');
        if (label === null)
            return;
        label.setAttribute('title', e.getAttribute('title'));
        label.setAttribute('data-toggle', 'tooltip');
    });
</script>
<?php

/**
 * output functions
 */
class Output {

    private static $instance;
    public $retVal;

    /**
     * constructor
     */
    private function __construct() {
	$this->retVal['status']["db"] = "ok";
    }

    /**
     * Returns the output instance or creates it.
     * @return Output output instance
     */
    public static function getInstance() {
	if (!self::$instance) {
	    self::$instance = new self();
	}

	return self::$instance;
    }

    /**
     * Adds data for use to output.
     * @param type $table
     * @param type $output
     */
    public function add($table, $output) {
	$this->retVal[$table] = $output;
    }

    /**
     * Adds an status for output
     * @param type $table status table
     * @param type $output message (use an array with 3 entries ("id", <code>, <message>))
     */
    public function addStatus($table, $output) {

	if ($output[1]) {
	    $this->retVal["status"]["debug"][] = $output;
	    $this->retVal["status"]["db"] = "failed";
	}

	$this->retVal['status'][$table] = $output;
    }

    /**
     * Generates the output for the browser. General you call this only once.
     */
    public function write() {

	header("Content-Type: application/json");
	header("Access-Control-Allow-Origin: *");
	# Rückmeldung senden
	if (isset($_GET["callback"]) && !empty($_GET["callback"])) {
	    $callback = $_GET["callback"];
	    echo $callback . "('" . json_encode($this->retVal, JSON_NUMERIC_CHECK) . "')";
	} else {
	    echo json_encode($this->retVal, JSON_NUMERIC_CHECK);
	}
    }

}
?>

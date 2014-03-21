<?php

$TABLES = array(
    "pizzas" => "id",
    "users" => "name"
);
$VIEWS = array();

# open db
$controller = new Controller();
        
$retVal["status"] = "nothing to do";

$tables = array_keys($TABLES);
foreach ($tables as $table) {
    $retVal = request($controller->getDB(), $table, $TABLES[$table], $retVal);
}

$viewKeys = array_keys($VIEWS);
foreach ($viewKeys as $view) {
    $viewData = $_GET[$view];

    if (isset($viewData)) {
	$retVal[$view] = getView($controller->getDB(), $viewData, $view, $TABLES[$view]);
    }
}

$http_raw = file_get_contents("php://input");

if (isset($http_raw) && !empty($http_raw)) {

    $obj = json_decode($http_raw, true);

    if (isset($_GET["adduser"])) {
        
        if (isset($obj["name"])) {
            $retVal["status"] = $controller->addUser($obj["name"]);
        } else {
            $retVal["error"] = $obj;
        }
    }
    if (isset($_GET["category-del"])) {
	$retVal["status"] = delCatMapping($db, $obj["torrent"], $obj["category"]);
    }
    if (isset($_GET["torrent-del"])) {
	$retVal["status"] = delTorrent($db, $obj["id"]);
    }

    if (isset($_GET["torrent-rename"])) {
	$retVal["status"] = renameTorrent($db, $obj["id"], $obj["name"]);
    }
    
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
# Rückmeldung senden

if (isset($_GET["callback"]) && !empty($_GET["callback"])) {
    $callback = $_GET["callback"];
    echo $callback . "('" . json_encode($retVal, JSON_NUMERIC_CHECK) . "')";
} else {
    echo json_encode($retVal, JSON_NUMERIC_CHECK);
}

function getView($db, $data, $tag, $search) {

    if (empty($data) || $data == "*") {
	$stm = $db->prepare("SELECT * FROM [" . $tag . "]");
	$stm->execute();
    } else {
	$stm = $db->prepare("SELECT * FROM [" . $tag . "] WHERE " . $search . " = :data");
	$stm->execute(array(
	    ':data' => $data
	));
    }
    $retVal = $stm->fetchAll(PDO::FETCH_ASSOC);
    $stm->closeCursor();

    return $retVal;
}

function getTables($db) {

    $tablesquery = $db->query("SELECT name FROM sqlite_master WHERE type='table';");
    $i = 0;
    $tablesRaw = $tablesquery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tablesRaw as $table) {

	$tables[$i] = $table['name'];
	$i++;
    }

    $tablesquery->closeCursor();

    return $tables;
}

function request($db, $tag, $searchTag, $retVal) {

    $tagObject = $_GET[$tag];

    if (isset($tagObject)) {

	// options
	if (!empty($tagObject)) {
	    $STMT = $db->prepare("SELECT * FROM " . $tag . " WHERE " . $searchTag . " = ?");
	    $STMT->execute(array($tagObject));
	} else {
	    $STMT = $db->query("SELECT * FROM " . $tag);
	}

	if ($STMT !== FALSE) {

	    $retVal[$tag] = $STMT->fetchAll(PDO::FETCH_ASSOC);
	} else {
	    $retVal["status"] = "Failed to create statement";
	}
	$STMT->closeCursor();
    }
    
return $retVal;
}


class Output {
    
    
    private static $instance;
    public $retVal;
    
    private function __construct() {
        $this->$retVal['status'] = array();
    }
    
    
    public static function getInstance() {
        if(!self::$instance) { 
            self::$instance = new self(); 
        } 

        return self::$instance; 
    }
    
    public function add($table, $output) {
        $htis->retVal[$table] = $output;
    }
    
    public function addStatus($table, $output) {
        $this->retVal['status'][$table] = $output;
    }
    
    public function output() {
        echo json_encode($retVal);
    }
}


class Controller {
    
    public $db; 
    
    public function __construct() {
        
        try {
            $this->db = new PDO("sqlite:pizza.db3");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        
        } catch (Exception $ex) {
            $retVal["status"] = $ex->getMessage();
            die(json_encode($retVal));
        }
    }
    
    public function prepare($sql) {
        
        $db = $this->getDB();
        
        try {
            return $db->prepare($sql);
            
        } catch (Exception $ex) {
            $retVal["status"] = $ex->getMessage();
            die(json_encode($retVal));
        }
    }
    
    public function execute($stm, $args) {
        try {
            $stm->execute($args);
            return $stm;
        } catch (Exception $ex) {
            $retVal["status"] = $ex->getMessage();
            die(json_encode($retVal));
        }
    }
    
    public function addUser($name) {
        
        $sql = "INSERT INTO users(name) VALUES(:name)";
        
        $stm = $this->prepare($sql);
        
        $stm = $this->execute($stm, array(
            ":name" => $name
        ));
        
        $error = $stm->errorInfo();
        $stm->closeCursor();
        return $error;
        
    }
    
    public function getUser($id) {
        
        $sql = "SELECT * FROM users WHERE name = :id";
        
        $stm = $this->prepare($sql);
        $stm = $this->execute($stm, array(
            ":id" => $id
        ));
        
        $output = Output::getInstance();
        
        $output->addStatus("users", $stm->errorInfo());
        $output->add("users", $stm->fetchAll(PDO::FETCH_ASSOC));
        
        $stm->closeCursor();
    }
    
    /**
     * 
     * @return PDO database
     */
    public function getDB() {
        
        return $this->db;
    }
}


class Pizza {
    
    function addPizza($name, $maxPersons, $price) {
        
        
    }
    
    function changePizza($userid, $to) {
        
    }
    
    function pay() {
        
    }
    
    function setReady() {
        
    }
    
    function buy() {
        
    }
}





?>

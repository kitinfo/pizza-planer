<?php

$TABLES = array(
    "pizzas" => "id",
    "users" => "name"
);
$VIEWS = array();

# open db
$controller = new Controller();
$out = Output::getInstance();

$retVal["status"] = "nothing to do";

$tables = array_keys($TABLES);
foreach ($tables as $table) {
    request($controller->getDB(), $table, $TABLES[$table], $retVal);
}

$viewKeys = array_keys($VIEWS);
foreach ($viewKeys as $view) {
    $viewData = $_GET[$view];

    if (isset($viewData)) {
        $retVal[$view] = getView($controller->getDB(), $viewData, $view, $TABLES[$view]);
    }
}

$http_raw = file_get_contents("php://input");
$pizza = new Pizza($controller);

if (isset($http_raw) && !empty($http_raw)) {

    $obj = json_decode($http_raw, true);

    if (isset($_GET["add-user"])) {

        if (isset($obj["name"])) {
            $retVal["status"] = $controller->addUser($obj["name"]);
        } else {
            $retVal["error"] = $obj;
        }
    }
    if (isset($_GET["add-pizza"])) {
        $pizza->addPizza($obj["name"], $obj["maxperson"], $obj["price"], $obj["tip"]);
        Output::getInstance()->output();
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

$out->add("old", $retVal);
$out->write();


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
        
        $out = Output::getInstance();

        if ($STMT !== FALSE) {

            $out->add($tag, $STMT->fetchAll(PDO::FETCH_ASSOC));
            $out->addStatus($tag, $STMT->errorInfo());
        } else {
            $out->addStatus($tag, "Failed to create statement");
        }
        $STMT->closeCursor();
    }

    return $retVal;
}

class Output {

    private static $instance;
    public $retVal;

    private function __construct() {
        $this->retVal['status'] = array();
        $this->retVal['status']["status"] = "ok";
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function add($table, $output) {
        $this->retVal[$table] = $output;
    }

    public function addStatus($table, $output) {
        $this->retVal['status'][$table] = $output;
    }

    public function write() {
        if (isset($_GET["callback"]) && !empty($_GET["callback"])) {
            $callback = $_GET["callback"];
            echo $callback . "('" . json_encode($this->retVal, JSON_NUMERIC_CHECK) . "')";
        } else {
            echo json_encode($this->retVal, JSON_NUMERIC_CHECK);
        }
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

    private function prepare($sql) {

        $db = $this->getDB();

        try {
            return $db->prepare($sql);
        } catch (Exception $ex) {
            $retVal["status"] = $ex->getMessage();
            die(json_encode($retVal));
        }
    }

    private function execute($stm, $args) {
        try {
            $stm->execute($args);
            return $stm;
        } catch (Exception $ex) {
            $retVal["status"] = $ex->getMessage();
            die(json_encode($retVal));
        }
    }

    public function exec($sql, $args) {
        $stm = $this->prepare($sql);
        return $this->execute($stm, $args);
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

    private $controller;

    public function __construct($controller) {
        $this->controller = $controller;
    }

    function addPizza($name, $maxPersons, $price, $tip) {

        $sql = "INSERT INTO pizzas(name, maxperson, price, tip) VALUES(:name, :maxperson, :price, :tip)";

        $con = $this->controller;

        $stm = $con->exec($sql, array(
            ":name" => $name,
            ":maxperson" => $maxPersons,
            ":price" => $price,
            ":tip" => $tip
        ));

        $out = Output::getInstance();

        $out->addStatus("addpizza", $stm->errorInfo());
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

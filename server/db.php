<?php

$controller = new Controller();
$out = Output::getInstance();
$pizza = new Pizza();
$user = new User();
main();

/**
 * main function
 */
function main() {
    global $out, $pizza, $controller, $user;

    $http_raw = file_get_contents("php://input");

    if (isset($_GET["pizza-users"])) {
        if (!empty($_GET["pizza-users"])) {
            $pizza->getPizzaUsersByID($_GET["pizza-users"]);
        } else {
            $pizza->getPizzaUsers();
        }
    }
    if (isset($_GET["pizzas"])) {
        if (!empty($_GET["pizzas"])) {
            $pizza->getPizza($_GET["pizzas"]);
        } else {
            $pizza->getPizzas();
        }
    }

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
            $pizza->addPizza($obj["name"], $obj["maxpersons"], $obj["price"], $obj["content"]);
        }
        if (isset($_GET["toggle-ready"])) {
            $user->setReady($obj["id"]);
        }
        if (isset($_GET["change-pizza"])) {
            $user->changePizza($obj["id"], $obj["to"]);
        }
        if (isset($_GET["pay"])) {
            if ($controller->checkSecret($obj["secret"])) {
                $user->pay($obj["id"], $obj["bool"]);
            }
        }
        if (isset($_GET["buy-pizza"])) {
            if ($controller->checkSecret($obj["secret"])) {
                $pizza->buyPizza($obj["id"]);
            }
        }
        if (isset($_GET["check-user"])) {
            $user->check($obj["id"], $obj["name"]);
        }
        if (isset($_GET["toggle-lock"])) {
            if ($controller->checkSecret($obj["secret"])) {
                $pizza->toggleLock($obj["id"]);
            }
        }
        if (isset($_GET["delete-pizza"])) {
            if ($controller->checkSecret($obj["secret"])) {
                $pizza->delete($obj["id"]);
            }
        }
    }

    $out->write();
}

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

/**
 * controller functions
 */
class Controller {

    private $db;

    /**
     * controller constructor (opens the database)
     */
    public function __construct() {

        try {
            $this->db = new PDO("sqlite:pizza.db3");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        } catch (PDOException $ex) {
            $retVal["status"]["db"] = $ex->getMessage();
            die(json_encode($retVal));
        }
    }

    /**
     * Checks the secret for admin stuff.
     * @param type $secret secret
     * @return boolean true if same as in database.
     */
    public function checkSecret($secret) {
        $sql = "SELECT * FROM system WHERE key = 'secret'";

        $stm = $this->exec($sql, array());

        $secretTable = $stm->fetch();
        $stm->closeCursor();
        if ($secretTable["value"] == $secret) {
            Output::getInstance()->addStatus("access", array("0", null, "granted"));
            return true;
        }
        Output::getInstance()->addStatus("access", array("99997", 97, "denied"));
        return false;
    }

    /**
     * Helper function for executing. Builds a prepared statement.
     * @param type $sql sql command.
     * @return type prepared statement which you can execute with execute()
     */
    private function prepare($sql) {

        $db = $this->getDB();

        try {
            $stm = $db->prepare($sql);
            if ($db->errorInfo()[1] != null) {
                $retVal["status"]["db"] = $db->errorInfo();
                die(json_encode($retVal));
            }
            return $stm;
        } catch (Exception $ex) {
            $retVal["status"]["db"] = $ex->getMessage();
            die(json_encode($retVal));
        }
    }

    /**
     * Helper function for executing the prepared statement.
     * @param type $stm prepared statement (@see prepare())
     * @param type $args argumens for the statement
     * @return type cursor
     */
    private function execute($stm, $args) {
        try {
            $stm->execute($args);
            return $stm;
        } catch (Exception $ex) {
            $retVal["status"]["db"] = $ex->getMessage();
            die(json_encode($retVal));
        }
    }

    /**
     * exec an action on the database
     * @param type $sql sql command
     * @param type $args arguments for the command
     * @return type database cursor (don't forget to close after doing ýour stuff)
     */
    public function exec($sql, $args) {
        $stm = $this->prepare($sql);
        return $this->execute($stm, $args);
    }

    /**
     * Adds the user to database.
     * @param type $name name of the user
     */
    public function addUser($name) {

        $sql = "INSERT INTO users(name) VALUES(:name)";

        $stm = $this->exec($sql, array(
            ":name" => $name
        ));

        $out = Output::getInstance();


        $out->addStatus("user", $stm->errorInfo());
        $out->add("user", $this->getDB()->lastInsertId());

        $stm->closeCursor();
    }

    /**
     * Returns the user with the given id
     * @param type $id id of the user
     */
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
     * Returns the database object
     * @return PDO database
     */
    public function getDB() {

        return $this->db;
    }

}

/**
 * functions for user actions
 */
class User {

    /**
     * Switches the user assignment to another pizza
     * @global type $out
     * @global Controller $controller
     * @param type $userid id of the user
     * @param type $to id of the pizza
     */
    function changePizza($userid, $to) {
        global $out;
        global $controller;

        $sql = "UPDATE users SET pizza = :pizza WHERE id = :id";

        $stm = $controller->exec($sql, array(
            ":pizza" => $to,
            ":id" => $userid
        ));

        $out->addStatus("change-pizza", $stm->errorInfo());
        $out->add("change-pizza", $controller->getDB()->lastInsertId());

        $stm->closeCursor();
    }

    /**
     * mark the user as had paid.
     * @global type $out
     * @global Controller $controller
     * @param type $id id of the user
     * @param type $bool true if the user had paid.
     */
    function pay($id, $bool) {
        global $out, $controller;

        $sql = "UPDATE users SET paid = :bool WHERE id = :id";

        $stm = $controller->exec($sql, array(
            ":id" => $id,
            ":bool" => $bool
        ));

        $out->addStatus("pay", $stm->errorInfo());
        $out->add("pay", $controller->getDB()->lastInsertId());

        $stm->closeCursor();
    }

    /**
     * Sets the ready flag for the user and checks then if the pizza is ready for lock.
     * @global Controller $controller
     * @global type $out
     * @param type $id id of the user
     * @param type $bool true if ready
     */
    function setReady($id) {
        global $controller, $out, $pizza;

        $sql = "UPDATE users SET ready = NOT ready WHERE id = :id AND pizza NOT NULL";

        $stm = $controller->exec($sql, array(
            ":id" => $id,
        ));

        $out->addStatus("set-ready", $stm->errorInfo());

        if ($stm->rowCount() < 1) {
            $out->addStatus("set-ready", array(
                "99996", 88, "Pizza not set."
            ));
        }

        $stm->closeCursor();

        $pizza->checkPizzaLock($id);
    }

    function check($id, $name) {
        global $controller, $out;

        $sql = "SELECT * FROM users WHERE id = :id AND name = :name";

        $stm = $controller->exec($sql, array(
            ":id" => $id,
            ":name" => $name
        ));

        $out->addStatus("check-user", $stm->errorInfo());

        $value = $stm->fetchAll(PDO::FETCH_ASSOC);
        if (count($value) == 1) {
            $out->add("check-user", "valid");
        } else {
            $out->add("check-user", "not valid");
        }
        $stm->closeCursor();
    }

}

/**
 * functions for pizza actions
 */
class Pizza {

    /**
     * buys a pizza if the pizza is locked or ready for lock.
     * @global type $out
     * @global Controller $controller
     * @param type $id id of the pizza
     */
    public function buyPizza($id) {
        global $out, $controller;

        if ($this->checkPizzaForLock($id)) {

            $sql = "UPDATE pizzas SET bought = 1 WHERE id = :id";

            $stm = $controller->exec($sql, array(
                ":id" => $id
            ));

            $out->addStatus("buy-pizza", $stm->errorInfo());
            $out->add("buy-pizza", $controller->getDB()->lastInsertId());

            $stm->closeCursor();
            return;
        }

        $out->addStatus("buy-pizza", array("99999", 99, "Pizza is not ready for buy"));
    }

    public function toggleLock($id) {

        // get lock status
        $this->setLock($id, $this->lockStatus($id));
    }

    private function lockStatus($id) {
        global $controller;

        $sql = "SELECT lock FROM pizzas WHERE id = :id";

        $stm = $controller->exec($sql, array(
            ":id" => $id
        ));
        $pizzas = $stm->fetch();

        if (count($pizzas) > 0) {
            
            return $pizzas[0];
        } else {
            return 0;
        }
    }

    /**
     * Unlocks a pizza and resets all ready states
     * @global type $out
     * @global Controller $controller
     * @param type $id id of the pizza
     */
    public function setLock($id, $lock) {
        global $out, $controller;

        $sql = "UPDATE pizzas SET lock = NOT :lock WHERE id = :id";

        $stm = $controller->exec($sql, array(
            ":id" => $id,
            ":lock" => $lock
        ));

        $out->addStatus("lock", $stm->errorInfo());
        $out->add("lock", $id);

        $stm->closeCursor();

        $sql = "UPDATE users SET ready = NOT :lock WHERE pizza = :id";

        $stm = $controller->exec($sql, array(
            ":id" => $id,
            ":lock" => $lock
        ));

        $out->addStatus("ready", $stm->errorInfo());
        $out->add("ready", $id);
        $stm->closeCursor();
    }

    /**
     * Deletes a pizza from database
     * @global Controller $controller
     * @global type $out
     * @param type $id id of the pizza.
     */
    public function delete($id) {
        global $controller, $out;

        $sql = "DELETE FROM pizzas WHERE id = :id";

        $stm = $controller->exec($sql, array(
            ":id" => $id
        ));

        $out->addStatus("delete-pizza", $stm->errorInfo());
        $out->add("delete-pizza", $id);

        $stm->closeCursor();
    }

    /**
     * Adds a pizza to database.
     * @global Controller $controller
     * @global type $out
     * @param type $name name of the pizza.
     * @param type $maxPersons how many persons can assign.
     * @param type $price the price of the pizza (with tip).
     * @param type $content Additional content like topping.
     */
    function addPizza($name, $maxPersons, $price, $content) {

        global $controller, $out;

        $sql = "INSERT INTO pizzas(name, maxpersons, price, content) VALUES(:name, :maxpersons, :price, :content)";


        $stm = $controller->exec($sql, array(
            ":name" => $name,
            ":maxpersons" => $maxPersons,
            ":price" => $price,
            ":content" => $content
        ));

        $out->addStatus("addpizza", $stm->errorInfo());
        $out->add("pizza", $controller->getDB()->lastInsertId());
    }

    /**
     * Get all pizzas and the users that are assigned.
     * @global type $out
     */
    function getPizzaUsers() {

        global $out;

        $result = array();

        $pizzas = $this->getPizzasQuery();

        foreach ($pizzas as $pizza) {
            $result[] = array(
                "users" => $this->getPizzaUsersByID($pizza["id"]),
                "id" => $pizza["id"],
                "lock" => $pizza["lock"],
                "bought" => $pizza["bought"],
                "maxpersons" => $pizza["maxpersons"]
            );
        }

        $out->add("pizzausers", $result);
    }

    /**
     * Get all pizzas in database.
     * @global type $out
     * @global Controller $controller
     */
    function getPizzas() {
        global $out, $controller;

        $sql = "SELECT * FROM pizzas";

        $stm = $controller->exec($sql, array());

        $out->addStatus("pizzas", $stm->errorInfo());
        $out->add("pizzas", $stm->fetchAll(PDO::FETCH_ASSOC));

        $stm->closeCursor();
    }

    /**
     * Get all pizzas in database (only for reuse).
     * @global Controller $controller
     * @return type
     */
    private function getPizzasQuery() {
        global $controller;

        $sql = "SELECT * FROM pizzas";

        $stm = $controller->exec($sql, array());

        $pizzas = $stm->fetchAll(PDO::FETCH_ASSOC);
        $stm->closeCursor();

        return $pizzas;
    }

    /**
     * Returns a pizza from database.
     * @global type $out
     * @global Controller $controller
     * @param type $id id of the pizza.
     * @return type
     */
    function getPizza($id) {
        global $out, $controller;

        $sql = "SELECT * FROM pizzas WHERE id = :id";

        $stm = $controller->exec($sql, array(
            ":id" => $id
        ));

        $out->addStatus("pizzas", $stm->errorInfo());
        $out->add("pizzas", $stm->fetchAll(PDO::FETCH_ASSOC));
        $stm->closeCursor();

        return $pizzas;
    }

    /**
     * Returns all users that wants this pizza.
     * @global Controller $controller
     * @param type $id id of the pizza
     * @return type
     */
    function getPizzaUsersByID($id) {
        global $controller;

        $sql = "SELECT name, ready, paid FROM users WHERE pizza = :id";

        $stm = $controller->exec($sql, array(
            ":id" => $id
        ));
        $users = $stm->fetchAll(PDO::FETCH_ASSOC);

        $stm->closeCursor();

        return $users;
    }

    /**
     * Check if the pizza is ready for lock.
     * @global Controller $controller
     * @global type $out
     * @param type $pizzaID id of the pizza
     * @return boolean true if ready for lock or is locked
     */
    function checkPizzaForLock($pizzaID) {
        global $controller, $out;

        $out->add("pizzaID", $pizzaID);

        $sql = "SELECT * FROM users WHERE NOT ready AND pizza = :pizza";

        $stm = $controller->exec($sql, array(
            ":pizza" => $pizzaID
        ));
        $notReadyUser = $stm->fetchAll(PDO::FETCH_ASSOC);
        if ($notReadyUser) {
            $out->addStatus("pizzalock", array("0", null, "notReady"));
            return false;
        }

        $maxPerson = $this->getMaxPerson($pizzaID);
        $currentPersons = $this->getPersonsByPizza($pizzaID);

        if ($maxPerson == $currentPersons) {
            $out->addStatus("pizzalock", array("0", null, "ready"));

            return true;
        }

        $out->addStatus("pizzalock", array("0", null, "notReady"));
        return false;
    }

    /**
     * Checks if the pizza assgined to the userid ist ready for lock.
     * If yes, lock the pizza.
     * @param type $userid
     */
    function checkPizzaLock($userid) {
        $pizzaID = $this->getPizzaFromUserID($userid);

        if ($this->checkPizzaForLock($pizzaID)) {
            $this->lockPizza($pizzaID, true);
        }
    }

    /**
     * Locks a pizza
     * @global type $out
     * @global Controller $controller
     * @param type $id id of the pizza
     * @param type $bool lock or not
     */
    function lockPizza($id, $bool) {
        global $out, $controller;

        $sql = "UPDATE pizzas SET lock = :bool WHERE id = :id";

        $stm = $controller->exec($sql, array(
            ":id" => $id,
            ":bool" => $bool
        ));

        $out->addStatus("lock-pizza", $stm->errorInfo());
        $out->add("lock-pizza", $controller->getDB()->lastInsertId());

        $stm->closeCursor();
    }

    /**
     * Returns how many persons can assign to the given pizza.
     * @global Controller $controller
     * @param type $id id of the pizza
     * @return int
     */
    function getMaxPerson($id) {
        global $controller;

        $sql = "SELECT maxpersons FROM pizzas WHERE id = :id";

        $stm = $controller->exec($sql, array(
            ":id" => $id
        ));
        $pizzas = $stm->fetch();
        if (count($pizzas) > 0) {
            return $pizzas[0];
        } else {
            return 0;
        }
    }

    /**
     * Returnshow many persons want this pizza
     * @global Controller $controller
     * @param type $pizzaID id of the pizza
     * @return int
     */
    function getPersonsByPizza($pizzaID) {
        global $controller;

        $sql = "SELECT * FROM users WHERE pizza = :id";

        $stm = $controller->exec($sql, array(
            ":id" => $pizzaID
        ));
        $pizzas = $stm->fetchAll(PDO::FETCH_ASSOC);

        if ($pizzas) {
            return count($pizzas);
        }
        return 0;
    }

    /**
     * Returns the pizza id from an user.
     * @global Controller $controller
     * @param Integer $id id of the user
     * @return int
     */
    function getPizzaFromUserID($id) {
        global $controller;

        $sql = "SELECT pizza FROM users WHERE id = :id";

        $stm = $controller->exec($sql, array(
            ":id" => $id
        ));
        $pizzas = $stm->fetch();
        if (count($pizzas) > 0) {
            return $pizzas[0];
        } else {
            return 0;
        }
    }

}

?>

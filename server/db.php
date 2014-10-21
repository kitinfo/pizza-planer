<?php

require_once 'database.php';
require_once 'output.php';


$db = new Database();
$out = Output::getInstance();
$pizza = new Pizza();
$user = new User();
main();

/**
 * main function
 */
function main() {
    global $out, $pizza, $db, $user;

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
		$retVal["status"] = $user->add($obj["name"]);
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

class Controller {

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

}

/**
 * functions for user actions
 */
class User {

    /**
     * Switches the user assignment to another pizza
     * @global type $out
     * @global Controller $db
     * @param type $userid id of the user
     * @param type $to id of the pizza
     */
    function changePizza($userid, $to) {
	global $out, $db, $pizza;

	if ($pizza->lockStatus($pizza->getPizzaFromUserID($userid))) {
	    $out->addStatus("set-ready", array(
		"75834", 87, "Pizza is locked"
	    ));
	    return;
	}

	if ($this->isReady($userid)) {
	    $out->addStatus("set-ready", array(
		"75833", 86, "User is ready."
	    ));
	    return;
	}

	$sql = "UPDATE users SET pizza = :pizza WHERE id = :id";

	$stm = $db->exec($sql, array(
	    ":pizza" => $to,
	    ":id" => $userid
	));

	$out->addStatus("change-pizza", $stm->errorInfo());
	$out->add("change-pizza", $db->getDB()->lastInsertId());

	$stm->closeCursor();
    }

    function isReady($id) {
	global $db;

	$sql = "SELECT ready FROM users WHERE id = :id";

	$stm = $db->exec($sql, array(
	    ":id" => $id
	));
	$ready = $stm->fetch();

	if (count($ready) > 0) {

	    return $ready[0];
	} else {
	    return false;
	}
    }

    /**
     * mark the user as had paid.
     * @global type $out
     * @global Controller $db
     * @param type $id id of the user
     * @param type $bool true if the user had paid.
     */
    function pay($id, $bool) {
	global $out, $db;

	$sql = "UPDATE users SET paid = :bool WHERE id = :id";

	$stm = $db->exec($sql, array(
	    ":id" => $id,
	    ":bool" => $bool
	));

	$out->addStatus("pay", $stm->errorInfo());
	$out->add("pay", $db->getDB()->lastInsertId());

	$stm->closeCursor();
    }

    /**
     * Sets the ready flag for the user and checks then if the pizza is ready for lock.
     * @global Controller $db
     * @global type $out
     * @param type $id id of the user
     * @param type $bool true if ready
     */
    function setReady($id) {
	global $db, $out, $pizza;


	if ($pizza->lockStatus($pizza->getPizzaFromUserID($id) == 1)) {
	    $out->addStatus("set-ready", array(
		"75834", 87, "Pizza is locked"
	    ));
	    return;
	}

	$sql = "UPDATE users SET ready = NOT ready WHERE id = :id AND pizza NOT NULL";

	$stm = $db->exec($sql, array(
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

    function check($token, $name) {
	global $db, $out;

	$sql = "SELECT * FROM users WHERE token = :token AND name = :name";

	$stm = $db->exec($sql, array(
	    ":token" => $token,
	    ":name" => $name
	));
	
	$out->addStatus("debug-token", $token);
	$out->addStatus("debug-name", $name);

	$out->addStatus("check-user", $stm->errorInfo());

	$value = $stm->fetchAll(PDO::FETCH_ASSOC);
	if (count($value) == 1) {
	    $out->add("check-user", "valid");
	} else {
	    $out->add("check-user", "not valid");
	}
	$stm->closeCursor();
    }

    /**
     * Adds the user to database.
     * @param type $name name of the user
     */
    public function add($name) {

	global $db, $out;
	
	$token = uniqid(session_id());
	
	
	$sql = "INSERT INTO users(name, source, token) VALUES(:name, :source, :token)";

	$stm = $db->exec($sql, array(
	    ":name" => $name,
	    ":source" => "local",
	    ":token" => $token
	));


	$out->addStatus("user", $stm->errorInfo());
	$out->add("user", $token);

	$stm->closeCursor();
    }

    /**
     * Returns the user with the given id
     * @param type $id id of the user
     */
    public function get($token) {

	global $out, $db;
	
	$sql = "SELECT * FROM users WHERE token = :token";

	$stm = $this->prepare($sql);
	$stm = $this->execute($stm, array(
	    ":token" => $token
	));

	$out->addStatus("users", $stm->errorInfo());
	$out->add("users", $stm->fetchAll(PDO::FETCH_ASSOC));

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
     * @global Controller $db
     * @param type $id id of the pizza
     */
    public function buyPizza($id) {
	global $out, $db;

	if ($this->checkPizzaForLock($id)) {

	    $sql = "UPDATE pizzas SET bought = 1 WHERE id = :id";

	    $stm = $db->exec($sql, array(
		":id" => $id
	    ));

	    $out->addStatus("buy-pizza", $stm->errorInfo());
	    $out->add("buy-pizza", $db->getDB()->lastInsertId());

	    $stm->closeCursor();
	    return;
	}

	$out->addStatus("buy-pizza", array("99999", 99, "Pizza is not ready for buy"));
    }

    public function toggleLock($id) {

	// get lock status
	$this->setLock($id, $this->lockStatus($id));
    }

    public function lockStatus($id) {
	global $db;

	$sql = "SELECT lock FROM pizzas WHERE id = :id";

	$stm = $db->exec($sql, array(
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
     * @global Controller $db
     * @param type $id id of the pizza
     */
    public function setLock($id, $lock) {
	global $out, $db;

	$sql = "UPDATE pizzas SET lock = NOT :lock WHERE id = :id";

	$stm = $db->exec($sql, array(
	    ":id" => $id,
	    ":lock" => $lock
	));

	$out->addStatus("lock", $stm->errorInfo());
	$out->add("lock", $id);

	$stm->closeCursor();

	$sql = "UPDATE users SET ready = NOT :lock WHERE pizza = :id";

	$stm = $db->exec($sql, array(
	    ":id" => $id,
	    ":lock" => $lock
	));

	$out->addStatus("ready", $stm->errorInfo());
	$out->add("ready", $id);
	$stm->closeCursor();
    }

    /**
     * Deletes a pizza from database
     * @global Controller $db
     * @global type $out
     * @param type $id id of the pizza.
     */
    public function delete($id) {
	global $db, $out;

	$sql = "DELETE FROM pizzas WHERE id = :id";

	$stm = $db->exec($sql, array(
	    ":id" => $id
	));

	$out->addStatus("delete-pizza", $stm->errorInfo());
	$out->add("delete-pizza", $id);

	$stm->closeCursor();
    }

    /**
     * Adds a pizza to database.
     * @global Controller $db
     * @global type $out
     * @param type $name name of the pizza.
     * @param type $maxPersons how many persons can assign.
     * @param type $price the price of the pizza (with tip).
     * @param type $content Additional content like topping.
     */
    function addPizza($name, $maxPersons, $price, $content) {

	global $db, $out;

	$sql = "INSERT INTO pizzas(name, maxpersons, price, content) VALUES(:name, :maxpersons, :price, :content)";


	$stm = $db->exec($sql, array(
	    ":name" => $name,
	    ":maxpersons" => $maxPersons,
	    ":price" => $price,
	    ":content" => $content
	));

	$out->addStatus("addpizza", $stm->errorInfo());
	$out->add("pizza", $db->getDB()->lastInsertId());
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
     * @global Controller $db
     */
    function getPizzas() {
	global $out, $db;

	$sql = "SELECT * FROM pizzas";

	$stm = $db->exec($sql, array());

	$out->addStatus("pizzas", $stm->errorInfo());
	$out->add("pizzas", $stm->fetchAll(PDO::FETCH_ASSOC));

	$stm->closeCursor();
    }

    /**
     * Get all pizzas in database (only for reuse).
     * @global Controller $db
     * @return type
     */
    private function getPizzasQuery() {
	global $db;

	$sql = "SELECT * FROM pizzas";

	$stm = $db->exec($sql, array());

	$pizzas = $stm->fetchAll(PDO::FETCH_ASSOC);
	$stm->closeCursor();

	return $pizzas;
    }

    /**
     * Returns a pizza from database.
     * @global type $out
     * @global Controller $db
     * @param type $id id of the pizza.
     * @return type
     */
    function getPizza($id) {
	global $out, $db;

	$sql = "SELECT * FROM pizzas WHERE id = :id";

	$stm = $db->exec($sql, array(
	    ":id" => $id
	));

	$out->addStatus("pizzas", $stm->errorInfo());
	$out->add("pizzas", $stm->fetchAll(PDO::FETCH_ASSOC));
	$stm->closeCursor();

	return $pizzas;
    }

    /**
     * Returns all users that wants this pizza.
     * @global Controller $db
     * @param type $id id of the pizza
     * @return type
     */
    function getPizzaUsersByID($id) {
	global $db;

	$sql = "SELECT name, ready, paid FROM users WHERE pizza = :id";

	$stm = $db->exec($sql, array(
	    ":id" => $id
	));
	$users = $stm->fetchAll(PDO::FETCH_ASSOC);

	$stm->closeCursor();

	return $users;
    }

    /**
     * Check if the pizza is ready for lock.
     * @global Controller $db
     * @global type $out
     * @param type $pizzaID id of the pizza
     * @return boolean true if ready for lock or is locked
     */
    function checkPizzaForLock($pizzaID) {
	global $db, $out;

	$out->add("pizzaID", $pizzaID);

	$sql = "SELECT * FROM users WHERE NOT ready AND pizza = :pizza";

	$stm = $db->exec($sql, array(
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
     * @global Controller $db
     * @param type $id id of the pizza
     * @param type $bool lock or not
     */
    function lockPizza($id, $bool) {
	global $out, $db;

	$sql = "UPDATE pizzas SET lock = :bool WHERE id = :id";

	$stm = $db->exec($sql, array(
	    ":id" => $id,
	    ":bool" => $bool
	));

	$out->addStatus("lock-pizza", $stm->errorInfo());
	$out->add("lock-pizza", $db->getDB()->lastInsertId());

	$stm->closeCursor();
    }

    /**
     * Returns how many persons can assign to the given pizza.
     * @global Controller $db
     * @param type $id id of the pizza
     * @return int
     */
    function getMaxPerson($id) {
	global $db;

	$sql = "SELECT maxpersons FROM pizzas WHERE id = :id";

	$stm = $db->exec($sql, array(
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
     * @global Controller $db
     * @param type $pizzaID id of the pizza
     * @return int
     */
    function getPersonsByPizza($pizzaID) {
	global $db;

	$sql = "SELECT * FROM users WHERE pizza = :id";

	$stm = $db->exec($sql, array(
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
     * @global Controller $db
     * @param Integer $id id of the user
     * @return int
     */
    function getPizzaFromUserID($id) {
	global $db;

	$sql = "SELECT pizza FROM users WHERE id = :id";

	$stm = $db->exec($sql, array(
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

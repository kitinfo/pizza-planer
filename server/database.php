<?php

require_once 'output.php';
/**
 * controller functions
 */
class Database {

    private $db;

    /**
     * controller constructor (opens the database)
     */
    public function __construct() {

        try {
            $this->db = new PDO("sqlite:pizza.db3");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
	    $this->db->query("PRAGMA foreign_key = ON");
        } catch (PDOException $ex) {
            $retVal["status"]["db"] = $ex->getMessage();
            die(json_encode($retVal));
        }
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
     * Returns the database object
     * @return PDO database
     */
    public function getDB() {

        return $this->db;
    }

}
?>
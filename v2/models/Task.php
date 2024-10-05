<?php
// models/Task.php

class Task {
    private $conn;
    private $table_name = "tasks";

    public $id;
    public $description;
    public $date_added;
    public $tag_id;
    public $completed;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function read() {
        $query = "SELECT t.id, t.description, t.date_added, t.tag_id, t.completed, g.name as tag_name 
                  FROM " . $this->table_name . " t 
                  LEFT JOIN tags g ON t.tag_id = g.id 
                  ORDER BY t.date_added DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function read_single($id) {
        $query = "SELECT t.id, t.description, t.date_added, t.tag_id, t.completed, g.name as tag_name 
                  FROM " . $this->table_name . " t 
                  LEFT JOIN tags g ON t.tag_id = g.id 
                  WHERE t.id = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET description=:description, date_added=:date_added, 
                      tag_id=:tag_id, completed=:completed";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":description", $data['description']);
        $stmt->bindParam(":date_added", date('Y-m-d H:i:s'));
        $stmt->bindParam(":tag_id", $data['tag_id']);
        $stmt->bindParam(":completed", $data['completed']);
        
        if($stmt->execute()) {
            return array("message" => "Task created successfully", "id" => $this->conn->lastInsertId());
        }
        return array("message" => "Unable to create task");
    }

    public function update($id, $data) {
        $query = "UPDATE " . $this->table_name . "
                  SET description=:description, tag_id=:tag_id, completed=:completed 
                  WHERE id=:id";
        
        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(":description", $data['description']);
        $stmt->bindParam(":tag_id", $data['tag_id']);
        $stmt->bindParam(":completed", $data['completed']);
        $stmt->bindParam(":id", $id);
        
        if($stmt->execute()) {
            return array("message" => "Task updated successfully");
        }
        return array("message" => "Unable to update task");
    }

    public function delete($id) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);
        
        if($stmt->execute()) {
            return array("message" => "Task deleted successfully");
        }
        return array("message" => "Unable to delete task");
    }
}
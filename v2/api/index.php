<?php
// api/index.php

require_once '../config/database.php';
require_once '../models/Task.php';
require_once '../models/Tag.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/Projects/List/v2/api/';
$request = substr($request_uri, strlen($base_path));

$request = explode('/', $request);


$input = json_decode(file_get_contents('php://input'), true);


$resource = $request[0] ?? null;
$id = $request[1] ?? null;

$database = new Database();
$db = $database->getConnection();

$task = new Task($db);
$tag = new Tag($db);

switch($resource) {
    case 'tasks':
        switch($method) {
            case 'GET':
                if ($id) {
                    $result = $task->read_single($id);
                } else {
                    $result = $task->read();
                }
                echo json_encode($result);
                break;
            case 'POST':
                $result = $task->create($input);
                echo json_encode($result);
                break;
            case 'PUT':
                if ($id) {
                    $result = $task->update($id, $input);
                    echo json_encode($result);
                }
                break;
            case 'DELETE':
                if ($id) {
                    $result = $task->delete($id);
                    echo json_encode($result);
                }
                break;
        }
        break;
    case 'tags':
        switch($method) {
            case 'GET':
                if ($id) {
                    $result = $tag->read_single($id);
                } else {
                    $result = $tag->read();
                }
                echo json_encode($result);
                break;
            case 'POST':
                $result = $tag->create($input);
                echo json_encode($result);
                break;
            // Implement PUT and DELETE methods for tags if needed
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(array("message" => "Resource not found"));
        break;
}
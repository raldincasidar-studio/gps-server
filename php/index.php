<?php
// index.php

// Set headers to allow cross-origin requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// If the request method is OPTIONS, return 200 OK immediately
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include the MongoDB library
require 'vendor/autoload.php'; // Ensure Composer's autoloader is included

// MongoDB connection string (your credentials)
$mongoUri = 'mongodb+srv://cmsgvargasynanicole:oLZB4xz00siJ4oHC@cluster0.o46sg.mongodb.net/';

try {
    $client = new MongoDB\Client($mongoUri);
    $database = $client->selectDatabase('your_database_name'); // Replace with your database name
    $collection = $database->selectCollection('devices');
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'Error connecting to MongoDB: ' . $e->getMessage()]);
    exit();
}

// Function to send JSON responses
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');

    // Convert BSON documents to arrays
    if ($data instanceof MongoDB\Model\BSONDocument || is_array($data)) {
        $data = bsonDocumentToArray($data);
    }

    echo json_encode($data);
    exit();
}

// Function to convert BSON documents to arrays
function bsonDocumentToArray($document) {
    $array = (array)$document;
    foreach ($array as $key => $value) {
        if ($value instanceof MongoDB\BSON\ObjectId) {
            $array[$key] = (string)$value;
        } elseif ($value instanceof MongoDB\BSON\UTCDateTime) {
            $array[$key] = $value->toDateTime()->format(DATE_ISO8601);
        } elseif (is_object($value)) {
            $array[$key] = bsonDocumentToArray($value);
        } elseif (is_array($value)) {
            $array[$key] = bsonDocumentToArray($value);
        }
    }
    return $array;
}

// Get the request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Split the path into parts
$pathParts = explode('/', $path);
$pathParts = array_filter($pathParts);
$pathParts = array_values($pathParts); // Reindex the array

// Routing logic
if (isset($pathParts[0]) && $pathParts[0] == 'api' && isset($pathParts[1]) && $pathParts[1] == 'devices') {
    // Handle the different methods and sub-paths
    if ($method == 'GET' && !isset($pathParts[2])) {
        // Route: GET /api/devices
        // Fetch all devices
        try {
            $cursor = $collection->find();
            $devices = [];
            foreach ($cursor as $document) {
                $devices[] = bsonDocumentToArray($document);
            }
            sendJsonResponse($devices);
        } catch (Exception $e) {
            sendJsonResponse(['message' => 'Error fetching devices: ' . $e->getMessage()], 500);
        }
    } elseif ($method == 'POST' && !isset($pathParts[2])) {
        // Route: POST /api/devices
        // Add a new device
        $input = json_decode(file_get_contents('php://input'), true);

        $name = $input['name'] ?? null;
        $latitude = $input['latitude'] ?? null;
        $longitude = $input['longitude'] ?? null;

        // Validate the request body
        if (!$name || !$latitude || !$longitude) {
            sendJsonResponse(['message' => 'Name, latitude, and longitude are required.'], 400);
        }

        try {
            $device = [
                'name' => $name,
                'latitude' => (float)$latitude,
                'longitude' => (float)$longitude,
                'date_added' => new MongoDB\BSON\UTCDateTime(),
                'date_updated' => new MongoDB\BSON\UTCDateTime()
            ];
            $result = $collection->insertOne($device);
            $device['_id'] = (string)$result->getInsertedId();
            sendJsonResponse($device, 201);
        } catch (Exception $e) {
            sendJsonResponse(['message' => 'Error adding device: ' . $e->getMessage()], 500);
        }
    } elseif ($method == 'PUT' && isset($pathParts[2])) {
        // Route: PUT /api/devices/:id
        // Edit device by ID
        $id = $pathParts[2];

        // Validate the ID
        try {
            $objectId = new MongoDB\BSON\ObjectId($id);
        } catch (Exception $e) {
            sendJsonResponse(['message' => 'Invalid ID'], 400);
        }

        $input = json_decode(file_get_contents('php://input'), true);

        $name = $input['name'] ?? null;
        $latitude = $input['latitude'] ?? null;
        $longitude = $input['longitude'] ?? null;

        $updateData = [];
        if ($name !== null) $updateData['name'] = $name;
        if ($latitude !== null) $updateData['latitude'] = (float)$latitude;
        if ($longitude !== null) $updateData['longitude'] = (float)$longitude;
        $updateData['date_updated'] = new MongoDB\BSON\UTCDateTime();

        try {
            $result = $collection->findOneAndUpdate(
                ['_id' => $objectId],
                ['$set' => $updateData],
                ['returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
            );

            if (!$result) {
                sendJsonResponse(['message' => 'Device not found'], 404);
            }

            sendJsonResponse($result);
        } catch (Exception $e) {
            sendJsonResponse(['message' => 'Error updating device: ' . $e->getMessage()], 500);
        }
    } elseif ($method == 'DELETE' && isset($pathParts[2])) {
        // Route: DELETE /api/devices/:id
        // Delete device by ID
        $id = $pathParts[2];

        // Validate the ID
        try {
            $objectId = new MongoDB\BSON\ObjectId($id);
        } catch (Exception $e) {
            sendJsonResponse(['message' => 'Invalid ID'], 400);
        }

        try {
            $result = $collection->deleteOne(['_id' => $objectId]);

            if ($result->getDeletedCount() === 0) {
                sendJsonResponse(['message' => 'Device not found'], 404);
            }

            sendJsonResponse(['message' => 'Device deleted successfully']);
        } catch (Exception $e) {
            sendJsonResponse(['message' => 'Error deleting device: ' . $e->getMessage()], 500);
        }
    } elseif ($method == 'GET' && isset($pathParts[2]) && $pathParts[2] == 'update') {
        // Route: GET /api/devices/update
        $id = $_GET['id'] ?? null;
        $latitude = $_GET['latitude'] ?? null;
        $longitude = $_GET['longitude'] ?? null;

        // Validate the request parameters
        if (!$id || !$latitude || !$longitude) {
            sendJsonResponse(['message' => 'ID, latitude, and longitude are required.'], 400);
        }

        // Validate the ID
        try {
            $objectId = new MongoDB\BSON\ObjectId($id);
        } catch (Exception $e) {
            // If the ID is not a valid ObjectId, create a new device
            $objectId = null;
        }

        try {
            if ($objectId) {
                // Check if the device exists
                $device = $collection->findOne(['_id' => $objectId]);

                if ($device) {
                    // Device exists, update its latitude and longitude
                    $updateData = [
                        'latitude' => (float)$latitude,
                        'longitude' => (float)$longitude,
                        'date_updated' => new MongoDB\BSON\UTCDateTime()
                    ];
                    $updatedDevice = $collection->findOneAndUpdate(
                        ['_id' => $objectId],
                        ['$set' => $updateData],
                        ['returnDocument' => MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
                    );
                    sendJsonResponse($updatedDevice);
                } else {
                    // Device does not exist, create a new one
                    $deviceData = [
                        'name' => 'GPS Device',
                        'latitude' => (float)$latitude,
                        'longitude' => (float)$longitude,
                        'date_added' => new MongoDB\BSON\UTCDateTime(),
                        'date_updated' => new MongoDB\BSON\UTCDateTime()
                    ];
                    $result = $collection->insertOne($deviceData);
                    $deviceData['_id'] = (string)$result->getInsertedId();
                    sendJsonResponse($deviceData, 201);
                }
            } else {
                // Invalid ID, create a new device
                $deviceData = [
                    'name' => 'GPS Device',
                    'latitude' => (float)$latitude,
                    'longitude' => (float)$longitude,
                    'date_added' => new MongoDB\BSON\UTCDateTime(),
                    'date_updated' => new MongoDB\BSON\UTCDateTime()
                ];
                $result = $collection->insertOne($deviceData);
                $deviceData['_id'] = (string)$result->getInsertedId();
                sendJsonResponse($deviceData, 201);
            }
        } catch (Exception $e) {
            sendJsonResponse(['message' => 'Error updating or adding device: ' . $e->getMessage()], 500);
        }
    } else {
        // Invalid route
        sendJsonResponse(['message' => 'Invalid route'], 404);
    }
} else {
    // Invalid route
    sendJsonResponse(['message' => 'Invalid route'], 404);
}

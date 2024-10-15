// index.js
const express = require('express');
const bodyParser = require('body-parser');
const cors = require('cors'); // Import the cors module
const mongoose = require('mongoose');

// MongoDB connection string (your credentials)
const mongoUri = 'mongodb+srv://cmsgvargasynanicole:oLZB4xz00siJ4oHC@cluster0.o46sg.mongodb.net/'; // Replace <username> and <password>

// Initialize Express app
const app = express();
const port = 3000;

// Middleware
app.use(cors()); // Enable CORS for all routes
app.use(express.json());

// Connect to MongoDB using Mongoose
mongoose.connect(mongoUri, {
    useNewUrlParser: true,
    useUnifiedTopology: true,
})
.then(() => {
    console.log('Connected to MongoDB');
})
.catch(err => {
    console.error('Error connecting to MongoDB:', err.message);
});

// Define a Mongoose schema for GPS devices
const deviceSchema = new mongoose.Schema({
    name: { type: String, required: true },
    latitude: { type: Number, required: true },
    longitude: { type: Number, required: true },
    date_added: { type: Date, default: Date.now },
    date_updated: { type: Date, default: Date.now }
});

// Create a Mongoose model for the devices
const Device = mongoose.model('Device', deviceSchema);

// Route to fetch all devices
app.get('/api/devices', async (req, res) => {
    try {
        const devices = await Device.find();
        res.json(devices);
    } catch (err) {
        res.status(500).json({ message: 'Error fetching devices' });
    }
});

// Route to add a new device
app.post('/api/devices', async (req, res) => {
    const { name, latitude, longitude } = req.body;

    // Validate the request body
    if (!name || !latitude || !longitude) {
        return res.status(400).json({ message: 'Name, latitude, and longitude are required.' });
    }

    try {
        // Create and save a new device
        const newDevice = new Device({ name, latitude, longitude });
        await newDevice.save();
        res.status(201).json(newDevice);
    } catch (err) {
        res.status(500).json({ message: 'Error adding device' });
    }
});

// Route to edit an existing device by ID
app.put('/api/devices/:id', async (req, res) => {
    const { name, latitude, longitude } = req.body;

    try {
        // Find and update the device
        const updatedDevice = await Device.findByIdAndUpdate(
            req.params.id,
            { name, latitude, longitude, date_updated: Date.now() },
            { new: true }
        );

        if (!updatedDevice) {
            return res.status(404).json({ message: 'Device not found' });
        }

        res.json(updatedDevice);
    } catch (err) {
        res.status(500).json({ message: 'Error updating device' });
    }
});

// Route to delete a device by ID
app.delete('/api/devices/:id', async (req, res) => {
    try {
        const deletedDevice = await Device.findByIdAndDelete(req.params.id);

        if (!deletedDevice) {
            return res.status(404).json({ message: 'Device not found' });
        }

        res.json({ message: 'Device deleted successfully' });
    } catch (err) {
        res.status(500).json({ message: 'Error deleting device' });
    }
});

// Route to update or add a GPS device based on ID
app.get('/api/devices/update', async (req, res) => {
    const { id, latitude, longitude } = req.query;

    // Validate the request parameters
    if (!id || !latitude || !longitude) {
        return res.status(400).json({ message: 'ID, latitude, and longitude are required.' });
    }

    try {
        // Check if the device exists
        let device = await Device.findById(id);

        if (device) {
            // Device exists, update its latitude and longitude
            device.latitude = parseFloat(latitude);
            device.longitude = parseFloat(longitude);
            device.date_updated = Date.now(); // Update the date_updated field
            await device.save();
            return res.json(device); // Respond with the updated device
        } else {
            // Device does not exist, do not add a new one
            return res.status(404).json({ message: 'Device not found' });
        }
    } catch (err) {
        console.error('Error updating device:', err);
        res.status(500).json({ message: 'Error updating device' });
    }
});

// Start the server
app.listen(port, () => {
    console.log(`Server running on http://localhost:${port}`);
});

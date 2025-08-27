# Elitemobile SIM Activation API Automation

This repository contains a script to **automate SIM card recharge and activation** using the **Elitemobile API**.  
It eliminates the need for manual activation by integrating directly with the Elitemobile platform and streamlining the entire process.

---

## 🚀 Features
- Automates **SIM card recharge** and **activation**
- Uses **Elitemobile REST API** for seamless processing
- Reduces manual work and improves operational efficiency
- Provides API integration for bulk SIM activations
- Handles error logging and API response tracking

---

## 🛠️ Tech Stack
- **Language:** PHP
- **API:** Elitemobile Recharge & Activation API
- **Environment:** Linux 

---

## 📂 Project Structure
```
elitemobile-sim-activation-api/
├── elitemobile_activation_script.php # Main script for activation
├── config.env # API credentials & environment variables
├── logs/ # API logs & response tracking
└── README.md # Project documentation
```

---

## ⚙️ Setup & Installation
```bash

###
 1. Clone the Repository

git clone https://github.com/your-username/elitemobile-sim-activation-api.git
cd elitemobile-sim-activation-api

2. Configure Environment Variables

Create a .env or config.env file and add your API credentials:

env
ELITEMOBILE_API_KEY=your_api_key_here
ELITEMOBILE_API_URL=https://api.elitemobile.example.com

3. Run the Script

# For Bash
php elitemobile_activation_script.php

```


📄 API Endpoints Used
Endpoint	Method	Description
/recharge	POST	Initiates SIM recharge request
/activate	POST	Activates the SIM card automatically
/status	GET	Fetches SIM activation status

🧩 Future Enhancements
Add real-time dashboard for monitoring activations

Integrate automated error notifications via email/Slack

Improve bulk activation handling

👨‍💻 Author
Mohd Hussain
Full Stack Developer • API Integrations Specialist

🏷️ License
This project is for internal automation and not intended for public use.

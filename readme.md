# 📡 API Fundamentals & Securing API Keys Demo 

> A comprehensive reference covering API concepts, Flask integration, secure key management, and HTTP tooling.
> This demonstration shows how a Flask API can be either **vulnerable** or **secured** depending on whether proper access controls (API keys) are implemented.


## What is an API?

An **API (Application Programming Interface)** is a defined contract/set of rules that allows two software systems to communicate. 
APIs expose **endpoints** — specific URLs that accept requests and return structured responses, they expose these endpoints typically in **JSON** or **XML** format.
HTTP methods define what action you want to perform on an endpoint. **The endpoint (URL) tells you where, and the method tells you what to do there.**

### Core HTTP Methods

| Method   | Purpose                        | Example Use              |
|----------|--------------------------------|--------------------------|
| `GET`    | Retrieve data                  | Fetch a user profile     |
| `POST`   | Send/create new data           | Submit a form            |
| `PUT`    | Update existing data (full)    | Replace a user record    |
| `PATCH`  | Update existing data (partial) | Change just an email     |  
| `DELETE` | Remove data                    | Delete a post            |

## This Project: Flask API

This project is a **Python Flask REST API** that integrates a service via an API key. 

### Setting Up the Project

**Install dependencies:**

```bash
# Windows & Linux
pip install -r requirements.txt
```

**Run the Flask server:**

```bash
# Linux / macOS
python app.py

# Windows (Command Prompt)
python secure_api.py

# Windows (PowerShell)
python secure_api.py
```

---

. API Key Security & Environment Variables

### ⚠️ Why You Must Never Hardcode API Keys

API keys are **credentials**. Hardcoding them in source code is one of the most common and dangerous security mistakes 

**Never do this:**
```python
api_key = "sk-abc123supersecretkey"
```

---

### ✅ The Correct Approach: Environment Variables

Environment variables store sensitive values **outside** your codebase, in the operating system's environment or a `.env` file that is never committed to version control.

**this project involves two programs [vulnerable_api](./vulnerable_api.py) and [secure_api](./secure_api.py) in the latter there is no integration of api keys therefore api endpoint "/data" is exposed but provision for api keys based off declared enviornmental variables in terminal or .env file if created is made in the secure_api**
**nb:the dotenv function should be uncommented if using .env**
  
 ## Setting Environment Variables

#### 🪟 Windows — PowerShell

```powershell
# Set for current session only
$env:API_KEY = "your_api_key_here"

# Verify it's set
echo $env:API_KEY

# Set permanently (user-level)
[System.Environment]::SetEnvironmentVariable("API_KEY", "your_api_key_here", "User")
```


#### 🐧 Linux / macOS — Bash

```bash
# Set for current terminal session
export API_KEY="your_api_key_here"

# Verify
echo $API_KEY

# Set permanently — add to ~/.bashrc or ~/.zshrc
echo 'export API_KEY="your_api_key_here"' >> ~/.bashrc
source ~/.bashrc
```

---

### Using a `.env` File (Recommended for Development)

Create a `.env` file in the project root:

```
API_KEY=your_api_key_here
SECRET_KEY=another_secret_value
DEBUG=True
```

Load it in Flask using `python-dotenv`:

```python
from dotenv import load_dotenv
import os

load_dotenv()  # Reads .env into environment

api_key = os.getenv("API_KEY")
```

**Critical:** Add `.env` to `.gitignore` immediately

> For production systems, consider **encrypting** secrets at rest using tools like **HashiCorp Vault** or **AWS KMS**. Keys should be rotated regularly and access should follow the **principle of least privilege**.

---

## 6. Using cURL to Access APIs

**cURL** (`curl`) is a command-line tool for making HTTP requests. It is the standard way to test and interact with APIs directly from a terminal ### Basic Syntax

```bash
curl [options] [URL]
```



### GET Request

```bash
# Linux / macOS / Windows PowerShell
curl https://api.example.com/data

# Windows Command Prompt
curl https://api.example.com/data
```

### Passing API Keys in Headers

The most secure and standard way to authenticate with an API is by including the key in the **request header**. Common header formats:

#### Bearer Token (OAuth / JWT style)
```bash
# Linux / macOS
curl -H "Authorization: Bearer your_api_key_here" https://api.example.com/data

# Windows PowerShell
curl -Headers @{"Authorization"="Bearer your_api_key_here"} https://api.example.com/data

# Windows Command Prompt
curl -H "Authorization: Bearer your_api_key_here" https://api.example.com/data
```

#### Custom API Key Header (e.g., OpenAI, some weather APIs)
```bash
# Linux / macOS
curl -H "x-api-key: your_api_key_here" https://api.example.com/data

# Windows PowerShell
curl -Headers @{"x-api-key"="your_api_key_here"} https://api.example.com/data
```

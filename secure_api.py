from flask import Flask,jsonify,request
import os

from flask.cli import load_dotenv
app = Flask(__name__)


SENSITIVE_INFORMATION ={
    "username": "admin",
    "password": "password123",
    "address": "abuja, nigeria",
    "api_key": "1234567890abcdef"}


#load_dotenv()  # Reads .env file in the same directory

api_key = os.getenv('API_KEY', "change_me")

def get_key():
    return request.headers.get('X-API-Key') == api_key

@app.get('/data') 

def data():
    if not get_key():
        return jsonify ({"error": "unauthorized"}), 401
    return jsonify(SENSITIVE_INFORMATION)

if __name__ == '__main__':
    app.run(host='127.0.0.1', port=3000)

##implementation with keys
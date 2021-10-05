# ojs-cilantro-plugin

Stellt eine einfache Web-API für OJS3 zur Verfügung, für diejenigen Funktionen, die Cilantro bzw. Salvia brauchen.

# Installation

* go to <ojs>/plugins/generic
* git clone <this> cilantro
* cd cilantro
* git submodule init
* git submodule update

# API

Every URL starts with <ojs-url>/plugins/generic/cilantro/api/

## Authorization

You need a HTTP-Header called "ojsAuthorization",
containing OJS-User and Password in the form
<username>:<password>
whereby both is base64encoded (to avoid special character issues).

Example: `bm9ib2R5:bm9wYXNzd29yZA==` means user `nobody` with pw `nopassword`, Linux commands example:
```bash
echo -n "nobody" | base64 -w 0
echo -n "nopassword" | base64 -w 0
```

## Endpoints

### journalInfo

   Gives back basic information about present journals as needed.
   
* **URL**
   
  /journalinfo
 
* **Method:**

  `GET`

* **URL Params**

  none

* **Data Params**

  none

* **Authorization**

  not required

* **Example Success Response:**

  * **Code:** 200  

  ```
  {  
    "task": "journalinfo",
    "success": true,  
    "warnings": ["a warning"],
    "data": {
        "test": {
            "id": "2",
            "key": "test",
            "locales": ["en_US", "de_DE"]
        }
    }    
  } 
  ```

* **Error Response:**

  * **Code:** 404 (or else, depends)  

  ```
  {
    "success": false,
    "message": "Error Reason",
    "warnings": ["first warning", "second warning"]
  }
  ```

* **Sample Call:**

  ```
    /journalInfo
  ```
  
### login
  
     Can be used to test login credentials. Does nothing excpet for login.
     
  * **URL**
     
    /journalinfo
   
  * **Method:**
  
    `GET`
  
  * **URL Params**
  
    none
  
  * **Data Params**
  
    none
  
  * **Authorization**
  
    **Required**
  
  * **Example Success Response:**
  
    * **Code:** 200  
  
    ```
    {  
      "task": "login",
      "success": true,  
      "warnings": ["a warning"]  
    } 
    ```
  
  * **Error Response:**
  
    * **Code:** 401
  
    ```
    {
      "success": false,
      "message": "Could not login with admin. ",,
      "warnings": ["a warning"]
    }
    ```
  
  * **Sample Call:**
  
    ```
      /login
    ```
    
### import
  
     Imports an Issue
     
  * **URL**
     
    /import/:journalcode
   
  * **Method:**
  
    `POST`
  
  * **URL Params**
  
    :journalcode - the journalCode "aa", "test" etc.
  
  * **Data Params**
  
    none
    
  * **POST Body**
  
    Contains full OJS3-Import-XML.
    See example/example.xml
  
  * **Authorization**
  
    **Required**
  
  * **Example Success Response:**
  
    * **Code:** 200  
  
    ```
    {  
      "published_articles": [
        "475",
        "476",
        "477"
      ],
      "published_issues": [
        "231"
      ],
      "task": "import",
      "success": true,
      "warnings": ["a warning"]  
    } 
    ```
  
  * **Example Error Response:**
  
    * **Code:** 404
  
    ```
    {
      "success": false,
      "message": "Import Failed.",
      "warnings": ["a warning"]
    }
    ```
  
  * **Sample Call:**
  
    ```
      /import
    ```


### get zenon ids
__deprecated__, moved into seperate plugin https://github.com/dainst/ojs-zenon-plugin.

retrieves a list fo all zenon-ids in the system with the links to the articles
	
 * **URL**
	 
	/zenon

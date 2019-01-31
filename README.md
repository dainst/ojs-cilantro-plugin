# ojs-cilantro-plugin

Stellt eine einfache Web-API für OMP3 zur Verfügung für diejenigen Funktionen, die Cilantro bzw. Salvia brauchen.

# Installation

* got to <ojs>/plugins/generic
* git clone <this>
* cd <this>
* git submodule init
* git submodule update

# API

Every URL starts with /<ojs-url>/plugins/generic/ojs-cilantro-plugin/api/

## Authorzation

You need a HTTP-Header called "ompAuthorization",
containg OJS-User and Password in the form
<username>:<password>
whereby both is base64encoded (to avoid special character issues).

Example: `bm9ib2R5:bm9wYXNzd29yZA==` means user `nobody` with pw `nopassword`

## Endpoints

### journalInfo

   Gives back basic information about present journals as needed by Salvia.
   
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
    /ojs-backup/plugins/generic/ojs-cilantro-plugin/api/journalInfo
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
      /ojs-backup/plugins/generic/ojs-cilantro-plugin/api/login
    ```
    
### import
  
     Can be used to test login credentials. Does nothing excpet for login.
     
  * **URL**
     
    /import/:journalcode
   
  * **Method:**
  
    `POST`
  
  * **URL Params**
  
    none
  
  * **Data Params**
  
    none
    
  * **POST Body**
  
    Contains full OJS2-Import-XML. (http://pkp.sfu.ca/ojs/dtds/2.4.8/native.dtd)
  
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
      /ojs-backup/plugins/generic/import/aa
    ```
    
### frontmatters

   Creates or Updates Frontmatters of PDF-Galleys (Representations) in the OJS.
   
* **URL**
   
  /frontmatters/:command/:id-type/
 
* **Method:**

  `GET`

* **URL Params**

   **Required:**
 
   `command=[create|replace]`
   
   _create_ adds a new Frontmatter page to the documents,
   _replace_ replaces the frist page of the document with a frontmatter.
   
   `id-type=[article|issue|galley|journal]`
  
    We provide some ids, wich kind of objects are meant by that? 

* **Data Params**

  **Required:**
  
  id=[comma-separated list of integers]
  
  Ids of the objects to work with
  
  **Optional:**
  
  thumbnails=[boolean]
  
  Shall we create some new thumbnails too?

* **Authorization**

  **Required**

* **Success Response:**

  * **Code:** 200  

  ```
  {  
    "task": "frontmatters",
    "success": true,  
    "warnings": ["some warning"]
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
    /ojs-backup/plugins/generic/ojs-cilantro-plugin/api/frontmatters/create/issue/?id=99
  ```
        

# ojs-cilantro-plugin ... for OMP!

Stellt eine einfache Web-API für OMP3 zur Verfügung für diejenigen Funktionen, die Cilantro bzw. Salvia brauchen.

# Installation

* git to <ojs>/plugins/generic
* git clone <this>
* cd <this>
* git submodule init
* git submodule update

# API

Every URL starts with /<omp-url>/plugins/generic/ojs-cilantro-plugin/api/

## Authorization

You need a HTTP-Header called "ompAuthorization",
containg OJS-User and Password in the form
<username>:<password>
whereby both is base64encoded (to avoid special character issues).

Example: `bm9ib2R5:bm9wYXNzd29yZA==` means user `nobody` with pw `nopassword`

## Endpoints

### pressInfo

   Gives back basic information about present journals as needed by Salvia.
   
* **URL**
   
  /pressinfo
 
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
    "task": "pressinfo",
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
    /books/plugins/generic/ojs-cilantro-plugin/api/pressInfo
  ```
  
### login
  
     Can be used to test login credentials. Does nothing excpet for login.
     
     Attention: As of OMP 3.1.1 appreantly not all information can be imported via XML.
     So it's not possible to automatically publish the imported books.
     A function for that could be designed.
     
  * **URL**
     
    /login
   
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
      /books/plugins/generic/ojs-cilantro-plugin/api/login
    ```
    
### import
  
     Imports a Monograph
     
  * **URL**
     
    /import/:presscode
   
  * **Method:**
  
    `POST`
  
  * **URL Params**
  
    :presscode - the code of the press. We currently use only one press, which is called "dai"
  
  * **Data Params**
  
    none
    
  * **POST Body**
  
    Contains full OMP-Import-XML. (http://pkp.sfu.ca/ojs/dtds/2.4.8/native.dtd)
    
    See example/example.xml
  
  * **Authorization**
  
    **Required**
  
  * **Example Success Response:**
  
    * **Code:** 200  
  
    ```
    {  
      "published_monographs": [
        "475",
        "476",
        "477"
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
      /books/plugins/generic/import/dai
    ```

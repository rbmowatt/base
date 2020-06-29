# RBMowatt Base

## Contents

-  [Architecture](#architecure)

-  [Services](#services)

-  [Controllers](#controllers)

-  [Models](#models)

-  [Usage](#usage)

-  [Querying The Api](#querying-the-api)

-  [Api Response](#apiresponse)



## Architecure

As the API contains no views we will use what I will call an **MSC** Pattern.

In this pattern a **Controller** will never communicate directly with a **Model** but instead use an intermediary **Service** to store and retrieve data.

All **Requests** are handled by **Controllers** and will always return an instance of **[ApiResponse]()**

### Services

The **Service** is the power engine behind every **Request** and **Response**. It accepts communications from an input and works with the **Models** to retrieve the information to be passed back to the client method.

-  **Services**

* map and expose additional **Sort Scopes** to the client method

	*  *solves issue of how do I Sort on properties foreign to the database definition of the object?*

		*  `protected $sortScopes`

* map and expose additional **Filter Scopes** to the client method 
	* solves issue of how do I Filter on properties foreign to the database definition of the object?

	*  `protected $scopes`

* Work with a single **Primary Model** but can work with many other models

	*  **Primary Model** represents the **Model** that a **Service** will perform its request upon unless told otherwise and allows us to implement inheritance from a **[Base Service](src/RBMowatt/Base/Services/BaseService.php)**.

* Any Service method is welcome to use any Model or Service to gather the information however I have tried to minimize using Models to acheive things I could do with other Services.

* The Primary Model should

	* Descend From **[Base Model](src/RBMowatt/Base/Services/BaseService.php)**

*  **[BaseService.php](src/RBMowatt/Base/Services/BaseService.php)**

	* Holds an assortment of reusable methods to retrieve data from an associated **Model**  `<$primaryModel>`

	*  *Ex.*

		* get

		* where

		* whereIn

		* delete

		* select

		* create

* By extending **[BaseService](src/RBMowatt/Base/Services/BaseService.php)** and assigning a **Model** you are automatically creating an interface for a client to call a number of curated methods on said **Model** while avoiding creating dependencies between client and **Model**

*  *for example the database structure could change without affecting **Controllers** and other clients of the **Service** as long as the **Service** is adjusted to account for the change.*

### Controllers

-  **Controllers**

	- are intended to be very light and have a single goal which consists of the following actions.

		1. Accept **Request**

		2. Hand **Request** Data to **Query Parser**

		3. Hand Result of **Query Parser** to **Service**

		4. Package results into **[ApiResponse](src/RBMowatt/Base/Rest/ApiResponse.php)**

		5. Return **[ApiResponse](src/RBMowatt/Base/Rest/ApiResponse.php)** to **Client**

	- Follow **Laravel** conventions in terms of routing

		-  [https://laravel.com/docs/5.5/routing](https://laravel.com/docs/5.5/routing)

	- Extend [BaseApiContoller.php](src/RBMowatt/Base/Controllers/Api/AirBaseApiController.php)

	- Should **ALWAYS** have their **Dependencies** injected

	- except in the case of needing **CONSTANTS**

### Models

*  **Models**

* Extend **[BaseModel.php](src/RBMowatt/Base/Models/BaseModel.php)**

* Follow same rules as **Laravel**  **Eloquent**

	*  [https://laravel.com/docs/5.5/eloquent](https://laravel.com/docs/5.5/eloquent)

* Because of the large size certain relations and methods are generally split into a few **Traits** that also include other related relations and methods

	* SortOrderTrait

	* Relations Trait

	*  **Traits** are for organization and Reuse

## Usage

### Querying the API

*  **GET/WHERE** can be passed in one of 2 ways

	*  `?where=[key1=val1,key2=val2]`

	*  `?key1=val1&key2=val2`

*  **NOT EQUALS** can be passed but only in the first format shown above

	*  `?where=[key1!=val1,key2!=val2]`

*  **GT and LT** can be passed by applying the appropriate sign as the first character of the value

	*  `?where=[key1=<val1,key2=>val2]`

	*  `?key1=<val1&key2=>val2`

*  **OR EQUAL TO** is not covered yet

*  *You can query on any property of the resource/model as exposed or the additional parameters described in the query parameter section of each request*

*  **WITH** Returns relations and can be passed in one of 2 ways

	*  `?with=[relation1,relation2]`

	*  `?with[]=relation1&with[]=relation2`



*  **PAGINATION**

* use the reserved `"page"` and `"limit"` keywords

	*  `?limit=20&page=2`

	*  **DEFAULT** set is always **20**

*  **SORT**

	* Sort the results asc or desc based on an exposed resource property

	*  **Sort** key pattern = `{property}_{order (ASC|DESC)}`

		*  `?sort=property_direction`

		*  `?sort[]=property_direction&sort[]=property2_direction`

	*  **Sorts** will be applied in the order they are received

*  **SELECT**

*  **Select** fields to return, can be passed in one of two ways

	*  `?select=[id,type_id]`

	*  `?select[]=id&select[]=type_id`

*  **COUNT**

	* Select Only the **Count** of the recordset

	*  `?count=true`

	*  **NOTE**, this will return **ONLY** count.

		* There is no use in adding relations or filtering shows

*  **POST**

	*  **Post** Endpoints will accept both form data and json objects

	*  **CREATE and MAP**

		* For a clear view let's break posts down into two categories

			*  **CREATE**

				*  **Post** Endpoints follow standard **REST** resource format

				*  **Endpoints** are representative of the **Resource** you are creating

				* To create a new **User**

				*  `POST /api/user`

					* You will always be required to fulfill the minimum validation

			*  **MAP** 
				* **MAP** endpoints represent those resources you wish to attach to a parent resource


					* To create a new user/widget mapping

						*  `POST /api/user/{user_id}/widget`

							* The body can consist of a single id, an array of ids or a collection of contract resource objects with the id properties hydrated

							*  `{ contracts : id }`

							*  `{ contracts : [id1,id2,id3] }`

							*  `{ contracts : [ {id:1}, {id:2}, {id:3} ] }`


*  **DELETE**

	*  **Delete** Endpoints allow for one or many deletes based on entity pks

		*  `DELETE /api/user/3`

		*  `DELETE /api/user/[3,4,5]`

		*  `DELETE/api/user/{user_id}/widget/1`

		*  `DELETE /api/user/{user_id}/widget/[3,4,5]`

	*  **NOTE** : when deleting relations use the pk of the resource not the pivot id

### APIResponse

An **[ApiResponse](src/RBMowatt/Base/Rest/ApiResponse.php)** comes in a standardized format and include the following properties

*  `success`

	* indicates if response was successful

*  `href`

	* indicates endpoint

*  `app`

	* indicates app name

*  `uid`

	* id of the user issuing the request

*  `time`

	* time the request was received

*  `statusCode`

	* status code being returned

*  `responseId`

	* unique code for process that can be matched against log

*  `meta`

	* holds the meta and pagination info

*  `data`

	* holds the data payload

*  `error`

	* IF error exists, human readable message

*  `errorCode`

	*  **[Error Code](src/RBMowatt/Base/ErrorCodes.php)** Associated With message

	* This is not an `HTTP Status Code` but a system specific code

	*  **Error Codes** can be found at [src/RBMowatt/Base/ErrorCodes.php](src/RBMowatt/Base/ErrorCodes.php)

*  `version`

	* displays the version of the api the request is being run against


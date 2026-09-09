# RBMowatt Base

[![tests](https://github.com/rbmowatt/base/actions/workflows/tests.yml/badge.svg)](https://github.com/rbmowatt/base/actions/workflows/tests.yml)

Scaffold a Laravel 13 REST API from a base Service, Model and Controller. Filtering, relations, sorting and pagination come off the query string, and every response goes back in the same envelope.

Requires PHP 8.3 or 8.4 and Laravel 13.

## Contents
-  [Install](#install)

-  [What It Does](#what-it-does)

-  [Architecture](#architecture)

-  [Services](#services)

-  [Controllers](#controllers)

-  [Models](#models)

-  [Usage](#usage)

-  [Querying The Api](#querying-the-api)

-  [Api Response](#apiresponse)

-  [Tests](#tests)


## Install

Not on Packagist yet, so point composer at the repo:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/rbmowatt/base" }
]
```

```
composer require rbmowatt/base:dev-master
```

The service provider is picked up by package discovery, so there is nothing to add to your config.

## What It Does

The Base Package will allow you to easily scaffold and get a Laravel Api running.

With a few simple configuration variables you can easily set up any number of Controllers/Services and Models that talk to each other while offering a QueryParser and Response Object that standardize the way data is parsed and then returned.

It really is as simple as extending a few classes and near instantly having an api that can handle filters, scopes, relations and sorts.

Just Extend a Service, set a BaseModel for that Service and then have your Controller use the QueryParser to retrieve the data to be presented to Service in the proper format.

In full, that is three small classes:

```php
class Widget extends BaseModel
{
    protected $table = 'widgets';

    // Eloquent still guards mass assignment, so a Service create() needs this
    protected $fillable = ['name', 'type_id'];
}
```

```php
class WidgetService extends BaseService
{
    // filter params that map to a scope instead of a column
    protected $scopes = ['widget.type.id' => 'byWidgetType'];

    // sort params that map to a scope instead of a column
    protected $sortScopes = [];

    public function __construct(Widget $widget)
    {
        $this->primaryModel = $widget;
    }
}
```

```php
class WidgetController extends BaseApiController
{
    protected $queryParser;
    protected $widgets;

    public function __construct(ApiResponse $response, QueryParser $queryParser, WidgetService $widgets)
    {
        parent::__construct($response);
        $this->queryParser = $queryParser;
        $this->widgets = $widgets;
    }

    public function index()
    {
        try
        {
            $result = $this->widgets->where(
                $this->queryParser->getWheres(),
                [],
                $this->queryParser->getSorts(),
                $this->queryParser->getSelects(),
                $this->queryParser->getLimit(),
                $this->queryParser->getPage()
            );

            return $this->response->setMeta($result->getMeta())->ok($result->items());
        }
        catch(Exception $e)
        {
            return $this->response->exception($e);
        }
    }
}
```

That is a working `GET /api/widget` with `?type_id=2`, `?sort=name_DESC`, `?limit=20&page=2` and the standard envelope, no further wiring.

## Architecture

As the API contains no views we will use what I will call an **MSC** Pattern.

In this pattern a **Controller** will never communicate directly with a **Model** but instead use an intermediary **Service** to store and retrieve data.

All **Requests** are handled by **Controllers** and will always return an instance of **[ApiResponse](src/RBMowatt/Base/Rest/ApiResponse.php)**

### Services

The **Service** is the power engine behind every **Request** and **Response**. It accepts communications from an input and works with the **Models** to retrieve the information to be passed back to the client method.

-  **Services**

* map and expose additional **Sort Scopes** to the client method

	*  *solves issue of how do I Sort on properties foreign to the database definition of the object?*

		*  `protected $sortScopes`

		*  declare it on every Service even when empty, a sort that isn't a column looks it up and an undeclared property throws

* map and expose additional **Filter Scopes** to the client method 
	* solves issue of how do I Filter on properties foreign to the database definition of the object?

	*  `protected $scopes`

* Work with a single **Primary Model** but can work with many other models

	*  **Primary Model** represents the **Model** that a **Service** will perform its request upon unless told otherwise and allows us to implement inheritance from a **[Base Service](src/RBMowatt/Base/Services/BaseService.php)**.

* Any Service method is welcome to use any Model or Service to gather the information however I have tried to minimize using Models to achieve things I could do with other Services.

* The Primary Model should

	* Descend From **[Base Model](src/RBMowatt/Base/Models/BaseModel.php)**

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

		-  [https://laravel.com/docs/13.x/routing](https://laravel.com/docs/13.x/routing)

	- Extend [BaseApiContoller.php](src/RBMowatt/Base/Controllers/Api/BaseApiController.php)

	-  **Breaking:** `$this->user` now resolves against `auth.defaults.guard`. It used to hardcode the `api` guard, which Laravel 11 removed from the stock `config/auth.php`, so a fresh app threw `Auth guard [api] is not defined` from the constructor of every subclass. If you were relying on the implicit `api` guard, set it explicitly:

		```php
		protected $guard = 'api';
		```

	- Should **ALWAYS** have their **Dependencies** injected

	- except in the case of needing **CONSTANTS**

### Models

*  **Models**

* Extend **[BaseModel.php](src/RBMowatt/Base/Models/BaseModel.php)**

* Follow same rules as **Laravel**  **Eloquent**

	*  [https://laravel.com/docs/13.x/eloquent](https://laravel.com/docs/13.x/eloquent)

* Set `$fillable` (or `$guarded`) like any Eloquent model, a Service `create()` goes through mass assignment

* Because of the large size certain relations and methods are generally split into a few **Traits** that also include other related relations and methods

	*  [RelationshipsTrait](src/RBMowatt/Base/Models/Traits/RelationshipsTrait.php)

	*  [PageAndLimitTrait](src/RBMowatt/Base/Models/Traits/PageAndLimitTrait.php)

	*  [DateCalculationTrait](src/RBMowatt/Base/Models/Traits/DateCalculationTrait.php)

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

#### Breaking changes to the envelope

If you are upgrading an app that already consumes this package, three things changed:

*  `success` is now always a real boolean. The error path used to emit the **string** `"true"` on success and the boolean `false` on failure, so a client doing a strict comparison saw two different types depending on the outcome.

*  Values are no longer coerced by `JSON_NUMERIC_CHECK`. Any numeric-looking string in the payload used to be rewritten on the way out: `"07005"` shipped as `7005`, `"000123"` as `123`, `"1.10"` as `1.1`, and an id past `2^53` came back as a number a JavaScript client cannot parse without losing the last digits. Strings now ship as strings. If a client relied on receiving numbers, cast on the client.

*  `error()` defaults to a `400` status instead of `200`. Pass the status explicitly if you want something else. `exception()` still defaults to `500` and `validationError()` to `422`.

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

## Tests

```
composer install
vendor/bin/phpunit
```

CI runs the same suite on PHP 8.3 and 8.4 for every push and pull request.


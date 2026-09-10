# RBMowatt Base

[![tests](https://github.com/rbmowatt/base/actions/workflows/tests.yml/badge.svg)](https://github.com/rbmowatt/base/actions/workflows/tests.yml)

Scaffold a Laravel REST API from a base Service, Model and Controller. Filtering, relations, sorting and pagination come off the query string, and every response goes back in the same envelope.

Requires PHP 8.3 or 8.4 and Laravel 11, 12 or 13.

## Contents
-  [Install](#install)

-  [What It Does](#what-it-does)

-  [Architecture](#architecture)

-  [Services](#services)

-  [Controllers](#controllers)

-  [Requests](#requests)

-  [Models](#models)

-  [Usage](#usage)

-  [Querying The Api](#querying-the-api)

-  [Api Response](#apiresponse)

-  [Tests](#tests)

-  [Static Analysis](#static-analysis)


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

    // A Service create()/update() writes only these. Anything else in the payload
    // is a MassAssignmentException, not a silently dropped key.
    protected $fillable = ['name', 'type_id'];
}
```

```php
class WidgetService extends BaseService
{
    // columns callers may filter on. Empty means none; a column being real is
    // not enough on its own
    protected $filterable = ['name', 'type_id'];

    // columns callers may sort by
    protected $sortable = ['name', 'type_id'];

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

	-  `$this->user` resolves against `auth.defaults.guard`. Laravel 11 dropped `api` from the stock `config/auth.php`, so pin a guard explicitly if you want one:

		```php
		protected $guard = 'api';
		```

	- Should **ALWAYS** have their **Dependencies** injected

	- except in the case of needing **CONSTANTS**

### Requests

Validation belongs in a **[BaseFormRequest](src/RBMowatt/Base/Requests/BaseFormRequest.php)**, not in the Controller and not in the Service. A Service is a query surface; the moment it starts checking whether a payload is well-formed it is doing two jobs. Type-hint the request on the action and Laravel validates during injection, before the action body runs.

```php
class WidgetStoreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type_id' => ['required', 'integer', 'exists:widget_types,id'],
        ];
    }

    // runs after the rules pass, so anything needing validated values or a
    // loaded record goes here rather than in authorize()
    protected function checkPermissions($validator)
    {
        if ($this->input('scope') === 'internal' && !$this->user()->is_admin) {
            $this->throwPermissionsException('Only an admin can do that.');
        }
    }
}
```

```php
public function store(WidgetStoreRequest $request)
{
    return $this->response->ok($this->widgetService->create($request->validated()));
}
```

Two things worth knowing:

* `validated()` returns only the keys the rules named, so it pairs with the model's `$fillable` as a first gate. A key that passes validation but is not fillable still raises `MassAssignmentException`

* **The Controller's `try/catch` cannot catch these.** Validation and `checkPermissions()` run while Laravel is resolving the request for injection, which is before the action body exists. The host app's exception handler is what renders them, so wire it up:

	```php
	// bootstrap/app.php
	->withExceptions(function (Exceptions $exceptions) {
	    $exceptions->render(function (RBMowatt\Base\Exception $e, $request) {
	        return ApiResponse::make()->exception($e, 400);
	    });
	})
	```

	`ValidationException` extends `RBMowatt\Base\Exception`, and `ApiResponse::exception()` routes it to `validationError()` for a `422` with the message bag attached.

A worked example lives at [example/Requests/ExampleStoreRequest.php](example/Requests/ExampleStoreRequest.php).

### Models

*  **Models**

* Extend **[BaseModel.php](src/RBMowatt/Base/Models/BaseModel.php)**

* Follow same rules as **Laravel**  **Eloquent**

	*  [https://laravel.com/docs/13.x/eloquent](https://laravel.com/docs/13.x/eloquent)

* Set `$fillable` (or `$guarded`) like any Eloquent model. A Service `create()` and `update()` check every payload key against it and throw `MassAssignmentException` on the first key the model does not accept — they do not drop it quietly the way `fill()` does. A model that declares neither is totally guarded, which is Eloquent's own default, and will reject everything until you list what is writable

* `softDelete()` delegates to Eloquent, so the model needs `use SoftDeletes;` and a `deleted_at` column. Without the trait it throws rather than writing a column nothing scopes on

* Give relation methods a return type (`: HasMany`, `: BelongsTo`, and so on). Relations are discovered from the declared type, never by calling methods to see what they return, so an untyped relation is invisible and `?with=` will reject it as unknown

* Because of the large size certain relations and methods are generally split into a few **Traits** that also include other related relations and methods

	*  [RelationshipsTrait](src/RBMowatt/Base/Models/Traits/RelationshipsTrait.php)

	*  [PageAndLimitTrait](src/RBMowatt/Base/Models/Traits/PageAndLimitTrait.php) — `limitTo`, `page` and `pt` build MySQL user-variable SQL through `DB::raw`. The grouping column is checked against the model's real columns and quoted before it goes in, and the group size must be a positive integer, because `setWheres()` will hand a mapped scope whatever the caller sent

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

*  *You can query on the columns the Service lists in `$filterable`, on any key it maps in `$scopes`, and on nothing else.* An unlisted key is a `400`, whether or not the column exists — the two cases are deliberately indistinguishable so the error cannot be used to walk the schema

	* Accepting any column on the table would make every column an oracle. Combined with the `>` / `<` operators and `?count=true`, `?password_hash=>$2y$10$K` is a valid comparison, the count answers it, and `$hidden` does not help because it governs serialization, not the where clause — a hash or a reset token comes out of that a character at a time

	* `$sortable` is the same list for `?sort=`, kept separate so a column can be orderable without being filterable

	* A scope declared with dots answers to underscores, so `widget.type.id` is reached as `?widget_type_id=`. When a **column of that same name also exists**, the key means two things and the request is rejected with `AmbiguousQueryParamException` rather than silently taking the scope. List the column in `$filterable` to make it win, or rename the scope. Sorting is unaffected — `$sortScopes` keys are matched exactly, with no underscore-to-dot step

*  **WITH** Returns relations and can be passed in one of 2 ways

	*  `?with=[relation1,relation2]`

	*  `?with[]=relation1&with[]=relation2`

	* Nested paths work — `?with=parts.supplier` — and **every segment** is resolved against the model at that level. Checking only the root would let one authorized relation expose everything reachable behind it

	* Paths are capped at `$maxRelationDepth` hops (default 3) because each hop is a query plus a count

	* The relation map each hop is checked against is cached per model class for 24h, the same as `columns()`. Discovery reflects the class and builds a relation object for every relation method on it to read the related class, which is far too much to repeat per request. Add or rename a relation and clear the cache on deploy

	* A relation you declare is a relation callers can pull. Use `$hidden` on the related model for fields that should not travel with it



*  **PAGINATION**

* use the reserved `"page"` and `"limit"` keywords

	*  `?limit=20&page=2`

	*  **DEFAULT** set is always **20**

	* `limit` is clamped to `QueryParser::MAX_LIMIT` (100) and coerced to an int. Unclamped it reaches `paginate()` verbatim, where `?limit=1000000` is a single-request table dump and `?limit=abc` arrives as a string. Subclass `QueryParser` and raise `$maxLimit` for an endpoint that needs bigger pages

	* A non-numeric `limit` or `page` falls back to the default rather than casting to `0`

	* For the cases pagination gets in the way of — a dropdown, an export — a Service has `all()`, which returns a `ServiceResultsCollection` with no pagination metadata. It is bounded: more than `$maxUnpaginated` matching rows (default 500) throws `UnboundedResultException` rather than handing back a truncated list that looks complete. Filters and sorts go through the same allowlists

*  **SORT**

	* Sort the results asc or desc based on a column listed in the Service's `$sortable`, or a key mapped in `$sortScopes`

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

	* request URI this response answers, taken from the framework's request. `null` when no request is bound

*  `app`

	* indicates app name

*  `uid`

	* id of the user issuing the request

*  `time`

	* ISO 8601 timestamp, with offset, of when the response was built

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

	* With `app.debug` off, only exceptions extending `RBMowatt\Base\Exception` are echoed back — those messages are written for the caller. Anything else renders as `ApiResponse::REDACTED_MESSAGE` and the real exception goes to the log. `QueryException` is why: its message carries the executed SQL with bindings already interpolated, so an unredacted envelope let a client enumerate the schema a column name at a time and read row data out of a failed write. Correlate a redacted body with its log line using `responseId`

	* With `app.debug` on, the message is followed by `FILE::` and `LINE::` as before. Do not run production with debug on

*  `errorCode`

	*  **[Error Code](src/RBMowatt/Base/ErrorCodes.php)** Associated With message

	* This is not an `HTTP Status Code` but a system specific code

	*  **Error Codes** can be found at [src/RBMowatt/Base/ErrorCodes.php](src/RBMowatt/Base/ErrorCodes.php)

*  `version`

	* displays the version of the api the request is being run against

	* read from `config('app.version')`, which Laravel does not set for you. Add it to `config/app.php` or the field reports `undefined`

### Status codes and types

*  `ok()` returns `200` unless you pass another code. `error()` returns `400`, `exception()` `500`, and `validationError()` `422`

*  `success` is a real boolean, never a string, so a strict comparison on the client is safe

*  Payload values are not coerced. `JSON_NUMERIC_CHECK` is deliberately off, so `"07005"` stays `"07005"`, `"1.10"` stays `"1.10"`, and an id past `2^53` stays a string a JavaScript client can read without losing digits. Cast on the client if you need numbers

### Swapping the implementation

`ApiResponse` implements **[ApiResponseInterface](src/RBMowatt/Base/Rest/Interfaces/ApiResponseInterface.php)**, and `BaseServiceProvider` binds the interface to it. To ship your own envelope, implement the interface and rebind it in your app's provider:

```php
$this->app->bind(ApiResponseInterface::class, MyApiResponse::class);
```

Controllers type-hint the interface, so nothing else changes. The envelope fields themselves are set through `__get`/`__set`, not named methods, so they are not part of the interface.

### Headers and CORS

`ApiResponse` sets no CORS headers. Cross-origin access is the application's call, not the package's, so configure Laravel's `HandleCors` middleware and `config/cors.php` as usual. Anything you pass to `withHeaders()` on the response is still merged onto the rendered `JsonResponse`.

## Tests

```
composer update
vendor/bin/phpunit
```

`composer.lock` is not committed, so use `composer update` rather than `composer install`.

CI runs the same suite across PHP 8.3 and 8.4 against Laravel 11, 12 and 13 for every push and pull request. The framework version is pinned through testbench: 9 pulls Laravel 11, 10 pulls 12, 11 pulls 13.

## Static analysis

```
composer analyse
```

PHPStan runs at level 5 over `src`, with larastan supplying Laravel's own types so Eloquent's magic calls resolve. It runs as its own CI job and is expected to stay at zero errors.

The composer script passes `--memory-limit=1G`. Larastan resolves the whole framework to read Eloquent's types and needs well past PHP's stock 128M, so `vendor/bin/phpstan analyse` on a default CLI config dies with "PHPStan process crashed because it reached configured PHP memory limit" from a parallel worker. CI never saw it because `setup-php` leaves `memory_limit` at `-1`.


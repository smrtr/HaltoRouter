HaltoRouter
===========

HaltoRouter is a small but powerful routing class for PHP 5.3+. HaltoRouter is a hard fork of
[AltoRouter](https://github.com/dannyvankooten/AltoRouter) with the basepath functionality stripped out
and new hostgroup matching functionality built in.

 - Dynamic routing with named parameters
 - Reversed routing (url generation)
 - Host matching via flexible hostgroups
 - Flexible regular expression routing (inspired by [Sinatra](http://www.sinatrarb.com))

## Getting Started

 1. PHP 5.3.x is required
 2. Setup URL rewriting so that all requests go through a single php file
 3. Create an instance of Smrtr\HaltoRouter, add your hostgroups, map your routes and match the request.

```php
$router = new \Smrtr\HaltoRouter;

// add hostgroups
$router->addHostnames(array('www.example.com', 'public.example.com', 'example.com'), 'public');
$router->addHostname('private.example.com', 'private');

// map routes
$router->map('GET', '/hello-world', 'Index@helloWorld', 'intro', 'public');
$router->map('GET|POST', '/settings', 'Settings@index', 'settings', 'private');
$router->map('GET|POST', '/user/[i:id]/[delete|update:action], 'Users', 'modify_user', 'private');

// generate url
$router->generate('modify_user', array('id'=>5, 'action'=>'delete'));
```

You can use the following limits on your named parameters. HaltoRouter will create the correct regexes.
```php
    *                    // Match all request URIs
    [i]                  // Match an integer
    [i:id]               // Match an integer as 'id'
    [a:action]           // Match alphanumeric characters as 'action'
    [h:key]              // Match hexadecimal characters as 'key'
    [:action]            // Match anything up to the next / or end of the URI as 'action'
    [create|edit:action] // Match either 'create' or 'edit' as 'action'
    [*]                  // Catch all (lazy, stops at the next trailing slash)
    [*:trailing]         // Catch all as 'trailing' (lazy)
    [**:trailing]        // Catch all (possessive - will match the rest of the URI)
    .[:format]?          // Match an optional parameter 'format' - a / or . before the block is also optional
```

Some more complicated examples

```php
    /posts/[*:title][i:id]     // Matches "/posts/this-is-a-title-123"
    /output.[xml|json:format]? // Matches "/output", "output.xml", "output.json"
    /[:controller]?/[:action]? // Matches the typical /controller/action format
```

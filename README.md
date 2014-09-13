### Installation

Install `tor` first.

On Mac OS X, install typing `brew install tor`.

### Example

```php
<?php

$b = new Brutor([
	'times_per_ip' => 3,
	'times' => 'forever',
	'sleep_per_ip' => 10,
	'sleep' => 120,
	'random_ua'	=> true,
	'curl_request'	=> "'http://www.google.com' -X POST -H 'Origin: http://www.google.com'",
	'curl_continue_per_ip' => function($resp) {
		return !!preg_match("/something/i", $resp);
	}
]);

$b->start();
```

### Options

##### `curl_request`

The CURL shell request

##### `curl_continue_per_ip`

A callback that parses (optionally) the request and can break the requests per IP

##### `times_per_ip`

Set how many times make requests per IP

##### `times`

Set how many times make requets globally

##### `sleep_per_ip`

Set how many seconds sleep after each request per IP

##### `sleep`

Set how many seconds sleep after each grouped request

##### `random_ua`

Set to `true` to random user agents for each request


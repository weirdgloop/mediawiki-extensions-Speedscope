# Extension:Speedscope

The Speedscope extension allows
generating [speedscope profiles](https://github.com/jlfwong/speedscope/blob/main/src/lib/file-format-spec.ts)
for randomly selected requests and for specific requests through a URL parameter.

## Requirements

* MediaWiki 1.45
* [`php-excimer`](https://pecl.php.net/package/excimer) and `php-zlib`
* The [speedscope service](https://github.com/weirdgloop/speedscope-service) is required to store, aggregate and serve
  the profiles.

## Configuration options

| Name                                | Default value                               | Description                                                                                                                                                                                                                                   |
|-------------------------------------|---------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `$wgSpeedscopeEndpoint`             | `http://localhost:3000`                     | The endpoint of the speedscope service that will be used by the extension to log profiles. This must be a URL without a trailing slash. If `$wgSpeedscopePublicEndpoint` is not set, this will also be used for the URLs in the notification. |
| `$wgSpeedscopeEnvironment`          | `prod`                                      | The environment of the current request, e.g. `prod` or `dev`.                                                                                                                                                                                 |
| `$wgSpeedscopeExcludedEntryPoints`  | `[ 'cli' ]`                                 | A list of entry points that are excluded from sampling. This is compared against the value of the `MW_ENTRY_POINT` constant.                                                                                                                  |
| `$wgSpeedscopeExposeCPUInfo`        | `false`                                     | Whether to add the contents of `/proc/stat` to profiles.                                                                                                                                                                                      |
| `$wgSpeedscopeForcedParam`          | `forceprofile`                              | The name of the URL parameter that can be used to generate a forced profile and trigger the notification.                                                                                                                                     |
| `$wgSpeedscopePeriod`               | `[ 'forced' => 0.0001, 'sample' => 0.001 ]` | The sampling period in seconds used by excimer.                                                                                                                                                                                               |
| `$wgSpeedscopePublicEndpoint`       | `null`                                      | If set, this will override the value of `$wgSpeedscopeEndpoint` when generating URLs for the speedscope notification.                                                                                                                         |
| `$wgSpeedscopeSamplingRates`        | `[ 'prod' => 0.01 ]`                        | The percentage of requests that will be randomly selected and profiled. The value of `$wgSpeedscopeEnvironment` will be used as the key when retrieving this value.                                                                           |
| `$wgSpeedscopeToken`                | `null`                                      | The token that will be used to authenticate when sending the request to the speedscope service.                                                                                                                                               |
| `$wgSpeedscopeLogToStatsd`          | `false`                                     | Whether profile samples should be sent to Statsd.                                                                                                                                                                                             |
| `$wgSpeedscopeEnableParserProfiler` | `false`                                     | Whether the parser profiler should be enabled. This requires a patch to MediaWiki Core.                                                                                                                                                       |

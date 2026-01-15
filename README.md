## Download

### simple use where you are
```
composer require cryodrift/fw
```
                            
### create a empty project

```
composer create-project cryodrift/projecttpl yourprojectname
```                                                                       

## Installation


```
php vendor/bin/cryodrift.php /sys install
```

- edit `.env` and run command again


## Info

```
php vendor/bin/cryodrift.php
```

```
php vendor/bin/cryodrift.php /sys vars
php vendor/bin/cryodrift.php /sys env
php vendor/bin/cryodrift.php /sys config
```

## index.php (create your own, when not started as project)


```
copy vendor/cryodrift/fw/tool/cryodrift.php index.php
```

- replace autoloader parts with `require 'vendor/autoload.php'`
- change your `Main::$rootdir` 
- change your `Config::$....` vars
- create `cfg/` folder for overrides get more information from package `cryodrift/projecttpl`
- run `php index.php /sys install`




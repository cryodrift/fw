## cryodrift - framework

**Small PHP framework for CLI, SSR, SQLite, and zero-logic templates**

cryodrift/fw is a lean PHP framework designed for developers who want full control,
minimal abstractions, and a command-line driven workflow.
It combines server-side rendering, strict separation of concerns, and
powerful SQLite tooling in a small, explicit codebase.

## Philosophy

- Predictable, visible control flow
- Identical behavior in CLI and Web
- No logic in templates
- SQLite as a first-class database (with migrations)
- Clear boundaries between layers
- Small, readable codebases

## Design Principles

- Dependency Injection for everything
- Server-Side Rendering by default
- Minimal JavaScript, no frontend lock-in
- Per-user filesystem and database isolation
- Predictable bootstrap and error handling
- No globals or magic state
- Predictable, visible control flow

CryoDrift is meant to be read, understood, and modified without surprises.

## Mainclasses

- Config, Main, Core, Handler
- basically we run a Handlerchain that is defined in config
### common handlers

- Router, FileHandler (see base-config.php)

## Quickstart - WARNING remember THIS!

### first time setup (dir can be multiple)

- run this once (we dont need to migrate databases,download files everytime)
```bash
   php index.php -echo -sessionuser="you@localhost.lan" /sys install -a -dir=src
```

- run this everytime you change a config.php (regenerates the cached config files for cli and web)
```bash
   php index.php -echo -sessionuser="you@localhost.lan" /sys install -dir=src -dir=vendor/cryodrift  
```

### Install packages (runs Cli::install in each App)

- run this everytime you change the schema ()
```bash
   php index.php -echo -sessionuser="you@localhost.lan" /sys modules -dir=src/yourapp
```

### Commandline

- show routes and help
```bash
   php index.php
```


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




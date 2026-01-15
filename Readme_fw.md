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

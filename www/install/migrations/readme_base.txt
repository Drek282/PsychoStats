Naming convention:  [version]_[repo]_[date]_[order]_[name].sql

- The "version" is the "from" version for the sql.  The sql will update from this version.
- The "repo" will be "base" for the base repo, or the game/mod identifier for a module.
- The date is in yyyymmdd format, ie. 20201225.
- If there are multiple migrations sql for a specific day the "order" field will indicate processing order.  This must take into account any migrations sql in the modules.
- The "name" is the human readable name for the migration file.  It should be as short as possible and cannot contain any underscores, "_", as that is the delimiter when the file name is processed.

ie: 327b_base_20230122_00_mounds-of-fudge.sql

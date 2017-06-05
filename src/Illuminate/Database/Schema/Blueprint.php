<?php

namespace Illuminate\Database\Schema;

use Closure;
use Illuminate\Support\Fluent;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Grammars\Grammar;

class Blueprint
{
    /**
     * The table the blueprint describes.
     *
     * @var string
     */
    protected $table;

    /**
     * The columns that should be added to the table.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * The commands that should be run for the table.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * The storage engine that should be used for the table.
     *
     * @var string
     */
    public $engine;

    /**
     * The default character set that should be used for the table.
     */
    public $charset;

    /**
     * The collation that should be used for the table.
     */
    public $collation;

    /**
     * Whether to make the table temporary.
     *
     * @var bool
     */
    public $temporary = false;

    /**
     * Create a new schema blueprint.
     *
     * @param  string  $table
     * @param  \Closure|null  $callback
     * @return void
     */
    public function __construct($table, Closure $callback = null)
    {
        $this->table = $table;

        if (! is_null($callback)) {
            $callback($this);
        }
    }

    /**
     * Execute the blueprint against the database.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @param  \Illuminate\Database\Schema\Grammars\Grammar $grammar
     * @return void
     */
    public function build(Connection $connection, Grammar $grammar)
    {
        foreach ($this->toSql($connection, $grammar) as $statement) {
            $connection->statement($statement);
        }
    }

    /**
     * Get the raw SQL statements for the blueprint.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @param  \Illuminate\Database\Schema\Grammars\Grammar  $grammar
     * @return array
     */
    public function toSql(Connection $connection, Grammar $grammar)
    {
        $this->addImpliedCommands();

        $statements = [];

        // Each type of command has a corresponding compiler function on the schema
        // grammar which is used to build the necessary SQL statements to build
        // the blueprint element, so we'll just call that compilers function.
        foreach ($this->commands as $command) {
            $method = 'compile'.ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if (! is_null($sql = $grammar->$method($this, $command, $connection))) {
                    $statements = array_merge($statements, (array) $sql);
                }
            }
        }

        return $statements;
    }

    /**
     * Add the commands that are implied by the blueprint's state.
     *
     * @return void
     */
    protected function addImpliedCommands()
    {
        if (count($this->getAddedColumns()) > 0 && ! $this->creating()) {
            array_unshift($this->commands, $this->createCommand('add'));
        }

        if (count($this->getChangedColumns()) > 0 && ! $this->creating()) {
            array_unshift($this->commands, $this->createCommand('change'));
        }

        $this->addFluentIndexes();
    }

    /**
     * Add the index commands fluently specified on columns.
     *
     * @return void
     */
    protected function addFluentIndexes()
    {
        foreach ($this->columns as $column) {
            foreach (['primary', 'unique', 'index'] as $index) {
                // If the index has been specified on the given column, but is simply equal
                // to "true" (boolean), no name has been specified for this index so the
                // index method can be called without a name and it will generate one.
                if ($column->{$index} === true) {
                    $this->{$index}($column->name);

                    continue 2;
                }

                // If the index has been specified on the given column, and it has a string
                // value, we'll go ahead and call the index method and pass the name for
                // the index since the developer specified the explicit name for this.
                elseif (isset($column->{$index})) {
                    $this->{$index}($column->name, $column->{$index});

                    continue 2;
                }
            }
        }
    }

    /**
     * Determine if the blueprint has a create command.
     *
     * @return bool
     */
    protected function creating()
    {
        return collect($this->commands)->contains(function ($command) {
            return $command->name == 'create';
        });
    }

    /**
     * Indicate that the table needs to be created.
     *
     * @return \Illuminate\Support\Fluent
     */
    public function create()
    {
        return $this->addCommand('create');
    }

    /**
     * Indicate that the table needs to be temporary.
     *
     * @return void
     */
    public function temporary()
    {
        $this->temporary = true;
    }

    /**
     * Indicate that the table should be dropped.
     *
     * @return \Illuminate\Support\Fluent
     */
    public function drop()
    {
        return $this->addCommand('drop');
    }

    /**
     * Indicate that the table should be dropped if it exists.
     *
     * @return \Illuminate\Support\Fluent
     */
    public function dropIfExists()
    {
        return $this->addCommand('dropIfExists');
    }

    /**
     * Indicate that the given columns should be dropped.
     *
     * @param  array|mixed  $columns
     * @return \Illuminate\Support\Fluent
     */
    public function dropColumn($columns)
    {
        $columns = is_array($columns) ? $columns : (array) func_get_args();

        return $this->addCommand('dropColumn', compact('columns'));
    }

    /**
     * Indicate that the given columns should be renamed.
     *
     * @param  string  $from
     * @param  string  $to
     * @return \Illuminate\Support\Fluent
     */
    public function renameColumn($from, $to)
    {
        return $this->addCommand('renameColumn', compact('from', 'to'));
    }

    /**
     * Indicate that the given primary key should be dropped.
     *
     * @param  string|array  $index
     * @return \Illuminate\Support\Fluent
     */
    public function dropPrimary($index = null)
    {
        return $this->dropIndexCommand('dropPrimary', 'primary', $index);
    }

    /**
     * Indicate that the given unique key should be dropped.
     *
     * @param  string|array  $index
     * @return \Illuminate\Support\Fluent
     */
    public function dropUnique($index)
    {
        return $this->dropIndexCommand('dropUnique', 'unique', $index);
    }

    /**
     * Indicate that the given index should be dropped.
     *
     * @param  string|array  $index
     * @return \Illuminate\Support\Fluent
     */
    public function dropIndex($index)
    {
        return $this->dropIndexCommand('dropIndex', 'index', $index);
    }

    /**
     * Indicate that the given foreign key should be dropped.
     *
     * @param  string|array  $index
     * @return \Illuminate\Support\Fluent
     */
    public function dropForeign($index)
    {
        return $this->dropIndexCommand('dropForeign', 'foreign', $index);
    }

    /**
     * Indicate that the timestamp columns should be dropped.
     *
     * @return void
     */
    public function dropTimestamps()
    {
        $this->dropColumn('created_at', 'updated_at');
    }

    /**
     * Indicate that the timestamp columns should be dropped.
     *
     * @return void
     */
    public function dropTimestampsTz()
    {
        $this->dropTimestamps();
    }

    /**
     * Indicate that the soft delete column should be dropped.
     *
     * @return void
     */
    public function dropSoftDeletes()
    {
        $this->dropColumn('deleted_at');
    }

    /**
     * Indicate that the soft delete column should be dropped.
     *
     * @return void
     */
    public function dropSoftDeletesTz()
    {
        $this->dropSoftDeletes();
    }

    /**
     * Indicate that the remember token column should be dropped.
     *
     * @return void
     */
    public function dropRememberToken()
    {
        $this->dropColumn('remember_token');
    }

    /**
     * Rename the table to a given name.
     *
     * @param  string  $to
     * @return \Illuminate\Support\Fluent
     */
    public function rename($to)
    {
        return $this->addCommand('rename', compact('to'));
    }

    /**
     * Specify the primary key(s) for the table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @param  string|null  $algorithm
     * @return \Illuminate\Support\Fluent
     */
    public function primary($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('primary', $columns, $name, $algorithm);
    }

    /**
     * Specify a unique index for the table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @param  string|null  $algorithm
     * @return \Illuminate\Support\Fluent
     */
    public function unique($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('unique', $columns, $name, $algorithm);
    }

    /**
     * Specify an index for the table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @param  string|null  $algorithm
     * @return \Illuminate\Support\Fluent
     */
    public function index($columns, $name = null, $algorithm = null)
    {
        return $this->indexCommand('index', $columns, $name, $algorithm);
    }

    /**
     * Specify a foreign key for the table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @return \Illuminate\Support\Fluent
     */
    public function foreign($columns, $name = null)
    {
        return $this->indexCommand('foreign', $columns, $name);
    }

    /*
     * 表中创建 自动递增 无符号的 integer (4-byte) 类型列
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function increments($column)
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * Create a new auto-incrementing tiny integer (1-byte) column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function tinyIncrements($column)
    {
        return $this->unsignedTinyInteger($column, true);
    }

    /**
     * Create a new auto-incrementing small integer (2-byte) column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function smallIncrements($column)
    {
        return $this->unsignedSmallInteger($column, true);
    }

    /**
     * Create a new auto-incrementing medium integer (3-byte) column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function mediumIncrements($column)
    {
        return $this->unsignedMediumInteger($column, true);
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function bigIncrements($column)
    {
        return $this->unsignedBigInteger($column, true);
    }

    /*
     * 创建 char 类型的列
     *
     * @param  string  $column
     * @param  int  $length
     * @return \Illuminate\Support\Fluent
     */
    public function char($column, $length = null)
    {
        $length = $length ?: Builder::$defaultStringLength;

        return $this->addColumn('char', $column, compact('length'));
    }

    /*
     * 表中创建 varhcar 类型的列
     *
     * @param  string  $column
     * @param  int  $length
     * @return \Illuminate\Support\Fluent
     */
    public function string($column, $length = null)
    {
        $length = $length ?: Builder::$defaultStringLength;

        return $this->addColumn('string', $column, compact('length'));
    }

    /*
     * 表中创建 text 类型的列
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function text($column)
    {
        return $this->addColumn('text', $column);
    }

    /*
     * 表中创建 mediumtext 类型的列
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function mediumText($column)
    {
        return $this->addColumn('mediumText', $column);
    }

    /*
     * 表中创建 longtext 类型的列
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function longText($column)
    {
        return $this->addColumn('longText', $column);
    }

    /*
     * 创建 integer (4-byte) 类型的列，长度为 10
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Illuminate\Support\Fluent
     */
    public function integer($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    /*
     * 创建 tiny integer (1-byte) 类型的列，长度为 3
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Illuminate\Support\Fluent
     */
    public function tinyInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /*
     * 创建 small integer (2-byte) 类型的列，长度为 5
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Illuminate\Support\Fluent
     */
    public function smallInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /*
     * 创建 medium integer (3-byte) 类型的列，长度为 8
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Illuminate\Support\Fluent
     */
    public function mediumInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /*
     * 创建 big integer (8-byte) 类型的列，长度为 20
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return \Illuminate\Support\Fluent
     */
    public function bigInteger($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /*
     * 创建无符号的 integer (4-byte) 类型的列，长度为 10
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Illuminate\Support\Fluent
     */
    public function unsignedInteger($column, $autoIncrement = false)
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /*
     * 创建无符号的 tiny integer (1-byte) 类型的列，长度为 3
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Illuminate\Support\Fluent
     */
    public function unsignedTinyInteger($column, $autoIncrement = false)
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    /*
     * 创建无符号的 small integer (2-byte) 类型的列，长度为 5
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Illuminate\Support\Fluent
     */
    public function unsignedSmallInteger($column, $autoIncrement = false)
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    /*
     * 创建无符号的 medium integer (3-byte) 类型的列，长度为 8
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Illuminate\Support\Fluent
     */
    public function unsignedMediumInteger($column, $autoIncrement = false)
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    /*
     * 创建无符号的 big integer (8-byte) 类型的列，长度为 20
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return \Illuminate\Support\Fluent
     */
    public function unsignedBigInteger($column, $autoIncrement = false)
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /*
     * 创建 double 类型的列，默认长度为 8，小数点为 2
     *
     * @param  string  $column
     * @param  int     $total
     * @param  int     $places
     * @return \Illuminate\Support\Fluent
     */
    public function float($column, $total = 8, $places = 2)
    {
        return $this->addColumn('float', $column, compact('total', 'places'));
    }

    /*
     * 创建 double 类型的列，默认长度为 0，小数点为 0
     *
     * @param  string   $column
     * @param  int|null    $total
     * @param  int|null $places
     * @return \Illuminate\Support\Fluent
     */
    public function double($column, $total = null, $places = null)
    {
        return $this->addColumn('double', $column, compact('total', 'places'));
    }

    /*
     * 创建 decimal 类型的列，默认长度为 8，小数点为 2
     *
     * @param  string  $column
     * @param  int     $total
     * @param  int     $places
     * @return \Illuminate\Support\Fluent
     */
    public function decimal($column, $total = 8, $places = 2)
    {
        return $this->addColumn('decimal', $column, compact('total', 'places'));
    }

    /*
     * 创建 tinyint 类型的列，长度为 1
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function boolean($column)
    {
        return $this->addColumn('boolean', $column);
    }

    /*
     * 创建 enum 类型的列
     *
     * @param  string  $column
     * @param  array   $allowed
     * @return \Illuminate\Support\Fluent
     */
    public function enum($column, array $allowed)
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    /**
     * Create a new json column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function json($column)
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create a new jsonb column on the table.
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function jsonb($column)
    {
        return $this->addColumn('jsonb', $column);
    }

    /*
     * 创建 date 类型的列
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function date($column)
    {
        return $this->addColumn('date', $column);
    }

    /*
     * 创建 datetime 类型的列
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function dateTime($column)
    {
        return $this->addColumn('dateTime', $column);
    }

    /*
     * 创建 datetime 类型的列，值会包含时区信息
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function dateTimeTz($column)
    {
        return $this->addColumn('dateTimeTz', $column);
    }

    /*
     * 创建 time 类型的列
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function time($column)
    {
        return $this->addColumn('time', $column);
    }

    /*
     * 创建 time 类型的列，值会包含时区信息
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function timeTz($column)
    {
        return $this->addColumn('timeTz', $column);
    }

    /*
     * 表中创建 timestamp 类型的列
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function timestamp($column)
    {
        return $this->addColumn('timestamp', $column);
    }

    /*
     * 创建 timestamp 类型的列，值会包含时区信息
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function timestampTz($column)
    {
        return $this->addColumn('timestampTz', $column);
    }

    /*
     * 添加可为空的 创建时间 和 更新时间 列，类型为 timestamp
     *
     * @return void
     */
    public function timestamps()
    {
        $this->timestamp('created_at')->nullable();

        $this->timestamp('updated_at')->nullable();
    }

    /*
     * timestamps 方法的别名
     *
     * @return void
     */
    public function nullableTimestamps()
    {
        $this->timestamps();
    }

    /*
     * 添加可为空的 创建时间 和 更新时间 列，类型为 timestamp, 值会包含时区信息
     *
     * @return void
     */
    public function timestampsTz()
    {
        $this->timestampTz('created_at')->nullable();

        $this->timestampTz('updated_at')->nullable();
    }

    /*
     * 添加可为空的 deleted_at 列，类型为 timestamp
     *
     * @return \Illuminate\Support\Fluent
     */
    public function softDeletes()
    {
        return $this->timestamp('deleted_at')->nullable();
    }

    /*
     * 添加可为空的 deleted_at 列，类型为 timestamp, 值会包含时区信息
     *
     * @return \Illuminate\Support\Fluent
     */
    public function softDeletesTz()
    {
        return $this->timestampTz('deleted_at')->nullable();
    }

    /*
     * 创建 blob 类型的列
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function binary($column)
    {
        return $this->addColumn('binary', $column);
    }

    /*
     * 创建 char 类型的列， 长度为 36
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function uuid($column)
    {
        return $this->addColumn('uuid', $column);
    }

    /*
     * 创建 varhcar 类型的列，长度为 45
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function ipAddress($column)
    {
        return $this->addColumn('ipAddress', $column);
    }

    /*
     * 创建 varhcar 类型的列，长度为 17
     *
     * @param  string  $column
     * @return \Illuminate\Support\Fluent
     */
    public function macAddress($column)
    {
        return $this->addColumn('macAddress', $column);
    }

    /**
     * Add the proper columns for a polymorphic table.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @return void
     */
    public function morphs($name, $indexName = null)
    {
        $this->unsignedInteger("{$name}_id");

        $this->string("{$name}_type");

        $this->index(["{$name}_id", "{$name}_type"], $indexName);
    }

    /**
     * Add nullable columns for a polymorphic table.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @return void
     */
    public function nullableMorphs($name, $indexName = null)
    {
        $this->unsignedInteger("{$name}_id")->nullable();

        $this->string("{$name}_type")->nullable();

        $this->index(["{$name}_id", "{$name}_type"], $indexName);
    }

    /*
     * 添加名为 remember_token 的字段列，类型为 varchar，长度为 100，可为空
     *
     * @return \Illuminate\Support\Fluent
     */
    public function rememberToken()
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * Add a new index command to the blueprint.
     *
     * @param  string        $type
     * @param  string|array  $columns
     * @param  string        $index
     * @param  string|null   $algorithm
     * @return \Illuminate\Support\Fluent
     */
    protected function indexCommand($type, $columns, $index, $algorithm = null)
    {
        $columns = (array) $columns;

        // If no name was specified for this index, we will create one using a basic
        // convention of the table name, followed by the columns, followed by an
        // index type, such as primary or index, which makes the index unique.
        $index = $index ?: $this->createIndexName($type, $columns);

        return $this->addCommand(
            $type, compact('index', 'columns', 'algorithm')
        );
    }

    /**
     * Create a new drop index command on the blueprint.
     *
     * @param  string  $command
     * @param  string  $type
     * @param  string|array  $index
     * @return \Illuminate\Support\Fluent
     */
    protected function dropIndexCommand($command, $type, $index)
    {
        $columns = [];

        // If the given "index" is actually an array of columns, the developer means
        // to drop an index merely by specifying the columns involved without the
        // conventional name, so we will build the index name from the columns.
        if (is_array($index)) {
            $index = $this->createIndexName($type, $columns = $index);
        }

        return $this->indexCommand($command, $columns, $index);
    }

    /**
     * Create a default index name for the table.
     *
     * @param  string  $type
     * @param  array   $columns
     * @return string
     */
    protected function createIndexName($type, array $columns)
    {
        $index = strtolower($this->table.'_'.implode('_', $columns).'_'.$type);

        return str_replace(['-', '.'], '_', $index);
    }

    /**
     * Add a new column to the blueprint.
     *
     * @param  string  $type
     * @param  string  $name
     * @param  array   $parameters
     * @return \Illuminate\Support\Fluent
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        $this->columns[] = $column = new Fluent(
            array_merge(compact('type', 'name'), $parameters)
        );

        return $column;
    }

    /**
     * Remove a column from the schema blueprint.
     *
     * @param  string  $name
     * @return $this
     */
    public function removeColumn($name)
    {
        $this->columns = array_values(array_filter($this->columns, function ($c) use ($name) {
            return $c['attributes']['name'] != $name;
        }));

        return $this;
    }

    /**
     * Add a new command to the blueprint.
     *
     * @param  string  $name
     * @param  array  $parameters
     * @return \Illuminate\Support\Fluent
     */
    protected function addCommand($name, array $parameters = [])
    {
        $this->commands[] = $command = $this->createCommand($name, $parameters);

        return $command;
    }

    /**
     * Create a new Fluent command.
     *
     * @param  string  $name
     * @param  array   $parameters
     * @return \Illuminate\Support\Fluent
     */
    protected function createCommand($name, array $parameters = [])
    {
        return new Fluent(array_merge(compact('name'), $parameters));
    }

    /**
     * Get the table the blueprint describes.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get the columns on the blueprint.
     *
     * @return array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * Get the commands on the blueprint.
     *
     * @return array
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * Get the columns on the blueprint that should be added.
     *
     * @return array
     */
    public function getAddedColumns()
    {
        return array_filter($this->columns, function ($column) {
            return ! $column->change;
        });
    }

    /**
     * Get the columns on the blueprint that should be changed.
     *
     * @return array
     */
    public function getChangedColumns()
    {
        return array_filter($this->columns, function ($column) {
            return (bool) $column->change;
        });
    }
}

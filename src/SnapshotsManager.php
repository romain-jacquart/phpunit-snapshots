<?php

namespace Madewithlove\PhpunitSnapshots;

use PHPUnit_Framework_TestCase;
use ReflectionClass;

class SnapshotsManager
{
    /**
     * @var PHPUnit_Framework_TestCase
     */
    protected static $suite;

    /**
     * @var array
     */
    protected static $assertionsInTest = [];

    /**
     * @param PHPUnit_Framework_TestCase $suite
     */
    public static function setSuite($suite)
    {
        static::$suite = $suite;
    }

    ////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////// SNAPSHOTS ///////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    public static function getSnapshotPath()
    {
        $parent = static::getTestSuitePath();
        $snapshotPath = sprintf('%s/__snapshots__/%s.snap', dirname($parent), basename($parent));

        return $snapshotPath;
    }

    /**
     * @param string|null $snapshotPath
     *
     * @return array
     */
    public static function getSnapshotContents($snapshotPath = null)
    {
        $snapshotPath = $snapshotPath ?: static::getSnapshotPath();

        // If we're in update mode, purge the snapshots to recreate
        // them from scratch if this is the first assertion
        // of this test suite
        $className = get_class(static::$suite);
        $assertions = array_values(static::$assertionsInTest[$className]);
        if (static::isUpdate() && $assertions === [0] && file_exists($snapshotPath)) {
            unlink($snapshotPath);
        }

        // If the folder doesn't yet exist, create it
        if (!is_dir(dirname($snapshotPath))) {
            mkdir(dirname($snapshotPath));
        }

        // If the file exists, fetch its contents, else
        // start from a new snapshot
        $contents = file_exists($snapshotPath)
            ? json_decode(file_get_contents($snapshotPath), true)
            : [];

        return $contents;
    }

    /**
     * @param string $identifier
     * @param mixed  $expected
     *
     * @return array
     */
    public static function upsertSnapshotContents($identifier, $expected)
    {
        $identifier = static::getAssertionIdentifier($identifier);
        $contents = static::getSnapshotContents();

        // If we already have a snapshot for this test, assert its contents
        // or update it if the --update flag was passed
        if (!isset($contents[$identifier]) || static::isUpdate()) {
            $contents[$identifier] = $expected;
            file_put_contents(static::getSnapshotPath(), json_encode($contents, JSON_PRETTY_PRINT));
        }

        return $contents[$identifier];
    }

    /**
     * Get an unique identifier for this particular assertion.
     *
     * @param string|null $identifier
     *
     * @return string
     */
    public static function getAssertionIdentifier($identifier = null)
    {
        // Keep a registry of how many assertions were run
        // in this test suite, and in this test
        $className = get_class(static::$suite);
        $methodName = static::$suite->getName();
        static::$assertionsInTest[$className][$methodName] = isset(static::$assertionsInTest[$className][$methodName])
            ? static::$assertionsInTest[$className][$methodName]
            : -1;

        $name = $methodName.'-'.++static::$assertionsInTest[$className][$methodName];
        $name = $identifier ? $name.': '.$identifier : $name;

        return $name;
    }

    ////////////////////////////////////////////////////////////////////////////////
    /////////////////////////////////// CONTEXT ////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    public static function getTestSuitePath()
    {
        return (new ReflectionClass(static::$suite))->getFileName();
    }

    /**
     * Check if PHPUnit is running in update mode.
     *
     * @return bool
     */
    public static function isUpdate()
    {
        return in_array('--update', $_SERVER['argv']);
    }
}

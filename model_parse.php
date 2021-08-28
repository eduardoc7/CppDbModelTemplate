<?php

$infile = $argv[1];
$datafile = $argv[2];
$jsondata = json_decode(file_get_contents($datafile));

if (!isset($jsondata))
{
    throw new Exception('json fail');
}

$TABLE_NAME = $jsondata->table;
$MODEL_NAME = $jsondata->model;
$FIELDS = $jsondata->fields;

function field_type_cpp($type)
{
    switch ($type) {
        case 'UInt64':
            return 'uint64_t';

        case 'String':
            return 'std::string';

        case 'timestamp':
            return 'std::chrono::system_clock::time_point';
    }
    return $type;
}

function field_type_get($type, $name)
{
    switch ($type) {
        case 'UInt64':
            return 'res->getUInt64("' . $name . '")';

        case 'String':
            return
                'res->getString("' . $name . '")';

        case 'timestamp':
            return 'CSql::string_to_system_clock(res->getString("' . $name . '"))';
    }

    throw new Exception('type error ' . $type);
}

function field_type_set($type, $name, $sname, $pos, $extra)
{
    switch ($type) {
        case 'UInt64':
            return 'prepstmt->setUInt64(' . $pos . ', ' . $sname . $extra . ')';

        case 'String':
            return 'prepstmt->setString(' . $pos . ', ' . $sname . $extra . ')';

        case 'timestamp':
            return 'prepstmt->setString(' . $pos . ', CSql::system_time_to_str(' . $sname . '))';
    }

    throw new Exception('type error ' . $type);
}

function extra_attribute_field($type)
{
    switch ($type) {
        case 'UInt64':
            return '{0}';
    }
    return '';
}

function nullablefieldtype_set($field, $sname, $pos)
{
    if ($field->nullable) {
        return $sname . ' ? '
            . field_type_set($field->type, $field->name, $sname, $pos, '.value()')
            . ' : prepstmt->setNull(' . $pos . ', 0)';
    }

    return field_type_set($field->type, $field->name, $sname, $pos, '');
}

function loop_fieldnames($fields)
{
    $first = true;
    $result = '';
    foreach ($fields as $k => $v) {
        if (!$first) {
            $result .= ', ';
        }
        $first = false;
        $result .= $v->name;
    }

    return $result;
}

function loop_fieldnames_update($fields)
{
    $first = true;
    $result = '';
    foreach ($fields as $k => $v) {
        if (!$first) {
            $result .= ', ';
        }
        $first = false;
        $result .= $v->name . ' = ?';
    }

    return $result;
}

function loop_fieldnames_interr($fields)
{
    $first = true;
    $result = '';
    foreach ($fields as $k => $v) {
        if (!$first) {
            $result .= ', ';
        }
        $first = false;
        $result .= '?';
    }

    return $result;
}

function extra_createupdate($fields)
{
    foreach ($fields as $k => $v) {
        if ($v->name == 'created_at') {
            return '    created_at = std::chrono::system_clock::now();
    updated_at = created_at;';
        }
    }

    return '';
}

function extra_update($fields)
{
    foreach ($fields as $k => $v) {
        if ($v->name == 'updated_at') {
            return '    updated_at = std::chrono::system_clock::now();';
        }
    }

    return '';
}

function nullablefieldtype_get($field)
{
    if ($field->nullable) {
        return 'res->isNull("' . $field->name . '") ? std::nullopt : '
        . nullablefieldtype($field)
           . '(' . field_type_get($field->type, $field->name) . ')';
    }

    return field_type_get($field->type, $field->name);
}


function nullablefieldtype($field)
{
    if ($field->nullable) {
        return 'std::optional<' . field_type_cpp($field->type) . '>';
    }

    return field_type_cpp($field->type);
}

include $infile;
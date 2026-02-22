<?php

namespace Afeefa\ApiResources\V2;

enum Operation: string
{
    case READ = 'read';
    case UPDATE = 'update';
    case CREATE = 'create';
}

const READ = Operation::READ;
const UPDATE = Operation::UPDATE;
const CREATE = Operation::CREATE;

<?php
/**
 * Created by PhpStorm.
 * User: shuwax
 * Date: 21.08.2018
 * Time: 13:50
 */

namespace App\Repository\TestingUser;


interface MyTestRepository
{
    function getAll();

    function getById($id);
}
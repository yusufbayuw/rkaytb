<?php

namespace App\Models;

use Illuminate\Validation\ValidationException;

class Role extends \Spatie\Permission\Models\Role
{
    public function save(array $options = [])
    {
        if ($this->exists && $this->getOriginal('name') === 'superadmin' && ($this->isDirty('name') || $this->isDirty('guard_name'))) {
            throw ValidationException::withMessages(['name' => 'Nama dan guard role superadmin tidak dapat diubah.']);
        }
        return parent::save($options);
    }

    public function delete()
    {
        // Guard before Spatie's deleting listeners detach user/permission pivots.
        if ($this->name === 'superadmin') throw ValidationException::withMessages(['name' => 'Role superadmin tidak dapat dihapus.']);
        return parent::delete();
    }
}

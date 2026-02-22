<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\Type\Type;

class TypeConfigurator
{
    private ?array $onlyFields = null;

    private bool $readOnly = false;

    /** @var FieldConfigurator[] */
    private array $fieldConfigs = [];

    public function only(array $fields): static
    {
        $this->onlyFields = $fields;
        return $this;
    }

    public function readOnly(): static
    {
        $this->readOnly = true;
        return $this;
    }

    public function field(string $name): FieldConfigurator
    {
        if (!isset($this->fieldConfigs[$name])) {
            $this->fieldConfigs[$name] = new FieldConfigurator();
        }
        return $this->fieldConfigs[$name];
    }

    public function apply(Type $type): void
    {
        // 1. Felder auf Subset einschränken
        if ($this->onlyFields !== null) {
            foreach (array_keys($type->getFields()->getEntries()) as $name) {
                if (!in_array($name, $this->onlyFields)) {
                    $type->getFields()->remove($name);
                }
            }
            foreach (array_keys($type->getUpdateFields()->getEntries()) as $name) {
                if (!in_array($name, $this->onlyFields)) {
                    $type->getUpdateFields()->remove($name);
                }
            }
            foreach (array_keys($type->getCreateFields()->getEntries()) as $name) {
                if (!in_array($name, $this->onlyFields)) {
                    $type->getCreateFields()->remove($name);
                }
            }
        }

        // 2. Mutations deaktivieren
        if ($this->readOnly) {
            foreach (array_keys($type->getUpdateFields()->getEntries()) as $name) {
                $type->getUpdateFields()->remove($name);
            }
            foreach (array_keys($type->getCreateFields()->getEntries()) as $name) {
                $type->getCreateFields()->remove($name);
            }
        }

        // 3. Feld-spezifische Konfiguration (required, validate) anwenden
        foreach ($this->fieldConfigs as $name => $fieldConfig) {
            if ($type->getUpdateFields()->has($name)) {
                $fieldConfig->applyToField($type->getUpdateField($name), Operation::UPDATE);
            }
            if ($type->getCreateFields()->has($name)) {
                $fieldConfig->applyToField($type->getCreateField($name), Operation::CREATE);
            }
        }
    }
}

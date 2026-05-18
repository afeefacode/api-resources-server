<?php

namespace Afeefa\ApiResources\V2;

use Afeefa\ApiResources\Exception\Exceptions\InvalidConfigurationException;
use Afeefa\ApiResources\Type\Type;
use Closure;

/**
 * Api-level Konfiguration eines Types.
 *
 * Bewusst schlanke Surface: `only()` als breiter Subset-Filter, `field()` als
 * Per-Feld-Werkzeug. Strukturelle Aenderungen (mode, restrictTo, Felder hinzufuegen)
 * gehoeren in den Type bzw. in overrideTypes().
 *
 * Aufrufe werden in Reihenfolge gesammelt und in `apply()` sequentiell ausgefuehrt.
 * Jede Operation validiert dabei gegen den aktuellen Stand der drei Bags — Verweise
 * auf nicht (mehr) existierende Felder werfen `InvalidConfigurationException`.
 */
class TypeConfigurator
{
    /** @var array<int, Closure(Type): void> */
    private array $operations = [];

    /** @var array<string, FieldConfigurator> */
    private array $fieldConfigs = [];

    public function only(array $fieldNames): static
    {
        if (count($fieldNames) === 0) {
            throw new InvalidConfigurationException(
                'TypeConfigurator only(): requires at least one field name.'
            );
        }
        $this->operations[] = function (Type $type) use ($fieldNames): void {
            $this->assertFieldsExistSomewhere($type, $fieldNames, 'only');

            foreach ($this->bagsOf($type) as $bag) {
                foreach (array_keys($bag->getEntries()) as $name) {
                    if (!in_array($name, $fieldNames, true)) {
                        $bag->remove($name);
                    }
                }
            }
        };
        return $this;
    }

    /**
     * Schaltet Felder pauschal read-only (entfernt sie aus UPDATE + CREATE).
     *
     * Ohne Argument trifft es alle zum Apply-Zeitpunkt im Type verbliebenen Felder
     * — die typische „Read-only Portal"-Konfiguration. Mit Array trifft es nur die
     * aufgelisteten Felder, unbekannte Namen werfen analog `only()`.
     */
    public function readOnly(?array $fieldNames = null): static
    {
        if ($fieldNames !== null && count($fieldNames) === 0) {
            throw new InvalidConfigurationException(
                'TypeConfigurator readOnly(): requires at least one field name, or omit the argument to target all fields.'
            );
        }
        $this->operations[] = function (Type $type) use ($fieldNames): void {
            if ($fieldNames !== null) {
                $this->assertFieldsExistSomewhere($type, $fieldNames, 'readOnly');
                $targets = $fieldNames;
            } else {
                $targets = $this->allFieldNames($type);
            }
            foreach ([$type->getUpdateFields(), $type->getCreateFields()] as $bag) {
                foreach ($targets as $name) {
                    if ($bag->has($name)) {
                        $bag->remove($name);
                    }
                }
            }
        };
        return $this;
    }

    public function field(string $name): FieldConfigurator
    {
        if (!isset($this->fieldConfigs[$name])) {
            $this->fieldConfigs[$name] = new FieldConfigurator();
        }
        $fc = $this->fieldConfigs[$name];
        // Jeder field()-Aufruf scheduled eine eigene Validierungs- und Apply-Operation.
        // Damit wird auch ein zweiter `field('note')` nach einem zwischengeschalteten
        // `only(['title'])` zur Apply-Zeit als Widerspruch erkannt — analog zum
        // Re-Aktivierungs-Check am Field selbst (Review Runde 1, Punkt 3).
        $this->operations[] = function (Type $type) use ($name, $fc): void {
            $this->assertFieldsExistSomewhere($type, [$name], 'field');
            $fc->applyTo($type, $name, strict: true);
        };
        return $this->fieldConfigs[$name];
    }

    public function apply(Type $type): void
    {
        foreach ($this->operations as $op) {
            $op($type);
        }
    }

    private function assertFieldsExistSomewhere(Type $type, array $fieldNames, string $context): void
    {
        $allNames = $this->allFieldNames($type);
        $unknown = array_values(array_diff($fieldNames, $allNames));
        if (count($unknown) === 0) {
            return;
        }
        $unknownList = implode(', ', $unknown);
        $knownList = $allNames ? implode(', ', $allNames) : '(none)';
        throw new InvalidConfigurationException(
            "TypeConfigurator {$context}(): unknown field(s) [{$unknownList}] on type {$type::type()}. "
            . "Known fields at this point: [{$knownList}]."
        );
    }

    /** @return string[] */
    private function allFieldNames(Type $type): array
    {
        $names = [];
        foreach ($this->bagsOf($type) as $bag) {
            foreach (array_keys($bag->getEntries()) as $name) {
                $names[$name] = true;
            }
        }
        return array_keys($names);
    }

    private function bagsOf(Type $type): array
    {
        return [
            $type->getFields(),
            $type->getUpdateFields(),
            $type->getCreateFields(),
        ];
    }
}

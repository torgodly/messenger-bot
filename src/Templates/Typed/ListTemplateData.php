<?php

namespace MessengerBot\Templates\Typed;

use InvalidArgumentException;

/**
 * List template: 2–4 rows, optional global buttons.
 */
readonly class ListTemplateData
{
    /**
     * @param  list<ListRow>  $rows
     * @param  list<array<string, mixed>>  $globalButtons
     */
    public function __construct(
        public array $rows,
        public ListTopElementStyle $topElementStyle = ListTopElementStyle::Compact,
        public array $globalButtons = [],
    ) {
        $n = count($this->rows);
        if ($n < 2 || $n > 4) {
            throw new InvalidArgumentException('List template requires between 2 and 4 rows.');
        }
        foreach ($this->rows as $i => $row) {
            if (! $row instanceof ListRow) {
                throw new InvalidArgumentException('Row at index '.$i.' must be a ListRow instance.');
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function elementPayloads(): array
    {
        return array_map(
            static fn (ListRow $row) => $row->toMetaFields(),
            $this->rows,
        );
    }

    /**
     * @return 'large'|'compact'
     */
    public function topStyleValue(): string
    {
        return $this->topElementStyle->value;
    }
}

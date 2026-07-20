'advance_allocations' =>
    $this->whenLoaded(
        'advanceAllocations',
        fn () =>
            $this->advanceAllocations->map(
                fn ($allocation) => [

                    'id' =>
                        $allocation->id,

                    'allocated_amount' =>
                        (float) $allocation->allocated_amount,

                    'sale_id' =>
                        $allocation->target_id,

                    'sale_no' =>
                        $allocation->target?->sale_no,
                ]
            )
    ),
<?php
namespace RS\NView;

class DTableRequest implements ArrayAccess
{

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $items;

    /**
     * DTableRequest constructor.
     */
    public function __construct(array $data = [])
    {
        $this->items = collect($data);
    }

    /**
     * Does the request have any columns
     *
     * @return bool
     */
    public function hasColumns(): bool
    {
        return $this->items->has('columns');
    }

    /**
     * Get columns from the request
     *
     * @return array
     */
    public function columns(): array
    {
        return $this->items->get('columns');
    }

    /**
     * Determine if an item exists at an offset.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->items->has($key);
    }

    /**
     * Get an item at a given offset.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items[$key];
    }

    /**
     * Set the item at a given offset.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->items->put($key,$value);
    }

    /**
     * Unset the item at a given offset.
     *
     * @param  string  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        $this->items->forget($key);
    }

}
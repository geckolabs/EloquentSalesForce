<?php

namespace Lester\EloquentSalesForce\Database;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JsonExpression;
use Illuminate\Database\Query\Grammars\Grammar;
use Lester\EloquentSalesForce\ServiceProvider;

class SOQLGrammar extends Grammar
{
	/**
	 * The components that make up a select clause.
	 *
	 * @var array
	 */
	protected $selectComponents = [
		'aggregate',
		'columns',
		'joins',
		'from',
		'wheres',
		'groups',
		'havings',
		'orders',
		'limit',
		'offset',
		'lock',
	];

	/**
	 * Wrap a single string in keyword identifiers.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function wrapValue($value)
	{
		return $value;
	}

	protected function unWrapValue($value)
	{
		return str_replace('`', '', $value);
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $where
	 * @return string
	 */
	protected function whereBasic(Builder $query, $where)
	{
		if (Str::contains(strtolower($where['operator']), 'not like')) {
			return sprintf(
				'(not %s like %s)',
				$this->wrap($where['column']),
				$this->wrap($where['value'])
			);
		}
		$string = parent::whereBasic($query, $where);
		$string = str_replace('`', '', $string);
		$string = str_replace('?', '\'?\'', $string);
		return $string;
	}

	/**
	 * Compile the "join" portions of the query.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $joins
	 * @return string
	 */
	protected function compileJoins(Builder $query, $joins)
	{
		return collect($joins)->map(function ($join) use ($query) {
			$table = $join->table;

			$columns = ServiceProvider::objectFields($table, ['*']);
			$columns = collect($columns)->implode(',');

			$table_p = $this->unWrapValue(str_plural($this->wrapTable($table)));
            return trim(", (select $columns from {$table_p})");
		})->implode(' ');
	}

	/**
	 * Format the where clause statements into one string.
	 *
	 * @param  \Illuminate\Database\Query\Builder  $query
	 * @param  array  $sql
	 * @return string
	 */
	protected function concatenateWhereClauses($query, $sql)
	{
		$conjunction = 'where';
		return $conjunction.' '.$this->removeLeadingBoolean(implode(' ', $sql));
	}

	/**
     * Compile an aggregated select clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $aggregate
     * @return string
     */
    protected function compileAggregate(Builder $query, $aggregate)
    {
        $column = $this->columnize($aggregate['columns']);
        // If the query has a "distinct" constraint and we're not asking for all columns
        // we need to prepend "distinct" onto the column name so that the query takes
        // it into account when it performs the aggregating operations on the data.
        if ($query->distinct && $column !== '*') {
            $column = 'distinct '.$column;
        }
        return 'select '.$aggregate['function'].'('.$column.') aggregate';
    }
}

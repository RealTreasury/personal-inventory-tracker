import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { render, useState, useEffect } from '@wordpress/element';
import Edit from './edit';

function InventoryTableFrontend() {
    const [ items, setItems ] = useState( [] );
    const [ page, setPage ] = useState( 1 );
    const [ search, setSearch ] = useState( '' );
    const [ totalPages, setTotalPages ] = useState( 1 );

    useEffect( () => {
        const params = new URLSearchParams( { page, search } );
        window.fetch( `/wp-json/pit/v2/items?${ params.toString() }` )
            .then( ( res ) => res.json() )
            .then( ( data ) => {
                setItems( data.items || [] );
                setTotalPages( data.total_pages || 1 );
            } );
    }, [ page, search ] );

    return (
        <div>
            <input
                type="search"
                value={ search }
                onChange={ ( e ) => setSearch( e.target.value ) }
                placeholder={ __( 'Search items…', 'personal-inventory-tracker' ) }
            />
            <table className="pit-inventory-table">
                <tbody>
                    { items.map( ( item ) => (
                        <tr key={ item.id }>
                            <td>{ item.name }</td>
                        </tr>
                    ) ) }
                </tbody>
            </table>
            <div className="pit-pagination">
                <button onClick={ () => setPage( page - 1 ) } disabled={ page === 1 }>
                    { __( 'Prev', 'personal-inventory-tracker' ) }
                </button>
                <span>{ `${ page } / ${ totalPages }` }</span>
                <button onClick={ () => setPage( page + 1 ) } disabled={ page === totalPages }>
                    { __( 'Next', 'personal-inventory-tracker' ) }
                </button>
            </div>
        </div>
    );
}

registerBlockType( 'personal-inventory-tracker/inventory-table', {
    edit: Edit,
    save() {
        return null;
    },
} );

window.addEventListener( 'DOMContentLoaded', () => {
    document
        .querySelectorAll( '.wp-block-personal-inventory-tracker-inventory-table' )
        .forEach( ( el ) => {
            render( <InventoryTableFrontend />, el );
        } );
} );

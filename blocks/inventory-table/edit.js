import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes } ) {
    return (
        <div { ...useBlockProps() }>
            <ServerSideRender
                block="personal-inventory-tracker/inventory-table"
                attributes={ attributes }
            />
        </div>
    );
}

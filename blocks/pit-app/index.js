import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';

registerBlockType( 'personal-inventory-tracker/pit-app', {
    edit: Edit,
} );

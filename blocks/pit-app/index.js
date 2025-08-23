import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';
import './style.css';

registerBlockType( 'pit/app', {
    edit: Edit,
    save: () => null,
} );

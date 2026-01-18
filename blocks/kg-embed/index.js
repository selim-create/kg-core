import { registerBlockType } from '@wordpress/blocks';
import './editor.scss';
import Edit from './edit';
import save from './save';

registerBlockType('kg-core/embed', {
    edit: Edit,
    save: save,
});

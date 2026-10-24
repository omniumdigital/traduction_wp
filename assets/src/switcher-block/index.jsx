import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { PanelBody, Placeholder, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from '../../blocks/switcher/block.json';

const variantOptions = [
	{ label: __( 'Dropdown', 'omnium-translate' ), value: 'dropdown' },
	{ label: __( 'Inline', 'omnium-translate' ), value: 'inline' },
	{ label: __( 'Floating', 'omnium-translate' ), value: 'floating' },
];

const displayOptions = [
	{ label: __( 'Flag and name', 'omnium-translate' ), value: 'flag-name' },
	{ label: __( 'Flag only', 'omnium-translate' ), value: 'flag' },
	{ label: __( 'Name only', 'omnium-translate' ), value: 'name' },
	{ label: __( 'Language code', 'omnium-translate' ), value: 'code' },
];

function Settings( { attributes, setAttributes } ) {
	return (
		<>
			<SelectControl
				label={ __( 'Layout', 'omnium-translate' ) }
				value={ attributes.variant }
				options={ variantOptions }
				onChange={ ( variant ) => setAttributes( { variant } ) }
			/>
			<SelectControl
				label={ __( 'Display', 'omnium-translate' ) }
				value={ attributes.display }
				options={ displayOptions }
				onChange={ ( display ) => setAttributes( { display } ) }
			/>
		</>
	);
}

registerBlockType( metadata.name, {
	...metadata,
	edit: ( properties ) => (
		<div { ...useBlockProps() }>
			<InspectorControls>
				<PanelBody title={ __( 'Switcher settings', 'omnium-translate' ) }>
					<Settings { ...properties } />
				</PanelBody>
			</InspectorControls>
			<Placeholder
				icon="translation"
				label={ __( 'Language selector', 'omnium-translate' ) }
				instructions={ __( 'Language links are rendered on the published page.', 'omnium-translate' ) }
			>
				<Settings { ...properties } />
			</Placeholder>
		</div>
	),
	save: () => null,
} );

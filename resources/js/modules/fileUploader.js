import * as FilePond from 'filepond';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';

FilePond.registerPlugin(
    FilePondPluginFileValidateType,
    FilePondPluginFileValidateSize
);

export function initializeFileUploader(inputEl, livewireComponent, config = {}) {
    const {
        acceptedFileTypes = [],
        multiple = false,
        wireModel = 'file',
    } = config;

    console.log('Initializing FilePond');
    FilePond.setOptions({
        allowMultiple: multiple,
        acceptedFileTypes,
        maxFileSize: '100MB',
        server: {
            process: (fieldName, file, metadata, load, error, progress, abort) => {
                console.log('Uploading file to Livewire');
                livewireComponent.upload(wireModel, file, load, error, progress);
            },
            revert: (filename, load) => {
                console.log('Reverting file in Livewire');
                livewireComponent.removeUpload(wireModel, filename, load);
            }
        }
    });

    return FilePond.create(inputEl);
}

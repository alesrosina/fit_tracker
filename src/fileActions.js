import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import { showError, showSuccess, showWarning } from '@nextcloud/dialogs'

const refresh = () => window.dispatchEvent(new CustomEvent('fit-tracker:refresh'))

export const fileName = path => (path ?? '').split('/').pop()

/**
 * Delete an activity or sleep session. The backend moves its .fit file to the
 * Nextcloud trash. Returns true on success.
 *
 * @param {'activities'|'sleep'} kind API collection
 * @param {string|number} id item id
 * @param {string} path the item's .fit file path, for error messages
 */
export async function deleteItem(kind, id, path) {
    try {
        await axios.delete(generateUrl(`/apps/fit_tracker/api/${kind}/${id}`))
    } catch (e) {
        if (e.response?.data?.error === 'file_not_deletable') {
            showError(t('fit_tracker', 'Could not delete {file}: you are not allowed to delete it. Ask the owner of the folder, or remove it from the folder yourself.', { file: fileName(path) }))
        } else {
            showError(t('fit_tracker', 'Could not delete {file}. Try again later.', { file: fileName(path) }))
        }
        return false
    }
    refresh()
    return true
}

/**
 * Import an item's .fit file again with the current parser, then open
 * whatever it became: the same kind of item, the other kind (activity ⇄
 * sleep), or the list when the file has no usable data.
 *
 * @param {'activities'|'sleep'} kind API collection
 * @param {string|number} id item id
 * @param {import('vue-router').Router} router app router
 * @return {Promise<boolean>} false when the re-import failed and the page stays as it was
 */
export async function reimportItem(kind, id, router) {
    let data
    try {
        ({ data } = await axios.post(generateUrl(`/apps/fit_tracker/api/${kind}/${id}/reimport`)))
    } catch (e) {
        if (e.response?.data?.error === 'file_missing') {
            showError(t('fit_tracker', 'The .fit file is no longer in your folder. Sync to remove this entry.'))
        } else {
            showError(t('fit_tracker', 'Could not re-import the file. Try again later.'))
        }
        return false
    }
    refresh()

    if (data.type === 'activity') {
        if (kind === 'sleep') showWarning(t('fit_tracker', 'The file is now recognized as an activity.'))
        else showSuccess(t('fit_tracker', 'Activity re-imported'))
        router.replace('/activity/' + data.id)
    } else if (data.type === 'sleep') {
        if (kind === 'activities') showWarning(t('fit_tracker', 'The file is now recognized as sleep.'))
        else showSuccess(t('fit_tracker', 'Sleep session re-imported'))
        router.replace('/sleep/' + data.id)
    } else {
        showWarning(t('fit_tracker', 'The file contains no activity or sleep data, so it is no longer shown.'))
        router.replace(kind === 'sleep' ? '/sleep' : '/')
    }
    return true
}

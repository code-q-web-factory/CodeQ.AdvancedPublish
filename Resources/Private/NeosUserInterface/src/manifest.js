import manifest from "@neos-project/neos-ui-extensibility";
import * as ReactDOM from 'react-dom';
import * as React from 'react';

import {takeLatest, take, put} from 'redux-saga/effects';
import {actionTypes} from '@neos-project/neos-ui-redux-store';

import {actions, reducer, selectors} from "./actions"
import {useSelector, useDispatch} from 'react-redux';

import PublishDropDown from './neos-ui/PublishDropDown'

const Modal = (({iframeUri}) => {
    const isOpen = useSelector(selectors.advancedPublishDialogOpen);
    const iframeRef = React.useRef(null);
    const dispatch = useDispatch();

    const setExit = React.useCallback(() => {
        const iframeWindow = iframeRef.current?.contentWindow;
        if (!iframeWindow) {
            return;
        }
        window.addEventListener('message', (event) => {
            if (event.data === 'closePublicationPopup') {
                dispatch(actions.toggleAdvancedPublishDialog({open: false}));
            }
        })
    }, [])

    if (!isOpen) {
        return "";
    }

    return <iframe
        ref={iframeRef}
        onLoad={setExit}
        style={{
            width: "100%",
            height: "100%",
            position: "absolute",
            zIndex: "999999",
            top: "0",
            background: "#000",
            border: "0"
        }}
        src={iframeUri}
    />;
})

manifest("CodeQ.AdvancedPublish", {}, (globalRegistry, {frontendConfiguration}) => {
    const frontendConfigurationElement: {
        enabled: boolean,
        isSuperEditor: boolean,
        iframeUri: string
    } = frontendConfiguration["CodeQ.AdvancedPublish"];
    if (!frontendConfigurationElement.enabled || frontendConfigurationElement.isSuperEditor === true) {
        return
    }

    function* afterPublish() {
        yield takeLatest(actionTypes.CR.Workspaces.PUBLISH, function* () {
            const {payload} = yield take(actionTypes.ServerFeedback.HANDLE_SERVER_FEEDBACK)
            if (!payload.feedbackEnvelope.feedbacks.some((feedback) => feedback.type === "Neos.Neos.Ui:Success")) {
                console.warn(`CodeQ.AdvancedPublish :: Publishing doesnt seem to have been successful. Aborting.`)
                return;
            }
            yield put(actions.toggleAdvancedPublishDialog())
        })
    }

    globalRegistry.get('containers').set('Modals/CodeQ.AdvancedPublish', () => ReactDOM.createPortal(
        <Modal iframeUri={frontendConfigurationElement.iframeUri}/>,
        document.body
    ));
    globalRegistry.get('sagas').set('CodeQ.AdvancedPublish/afterPublish', {saga: afterPublish});
    globalRegistry.get('reducers').set('CodeQ.AdvancedPublish', {reducer});

    globalRegistry.get('containers').set('PrimaryToolbar/Right/PublishDropDown', PublishDropDown);
});

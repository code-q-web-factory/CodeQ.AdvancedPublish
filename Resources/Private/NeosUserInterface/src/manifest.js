import manifest from "@neos-project/neos-ui-extensibility";
import * as ReactDOM from 'react-dom';
import * as React from 'react';

import { takeLatest, take, put } from 'redux-saga/effects';
import { actionTypes } from '@neos-project/neos-ui-redux-store';

import { actions, reducer, selectors } from "./actions"
import {connect} from 'react-redux';

const Modal = (({open, iframeUri}) => {
	if (!open) {
		return "";
	}

	return <iframe style={{width: "100%", height: "100%", position: "absolute", zIndex: "999999", top: "0", background: "#000", border: "0"}} src={iframeUri} />;
})

const ConnectedModal = connect((state) => ({open: selectors.advancedPublishDialogOpen(state)}))(Modal)

manifest("CodeQ.AdvancedPublish", {}, (globalRegistry, { store, frontendConfiguration }) => {
	function* afterPublish() {
		yield takeLatest(actionTypes.CR.Workspaces.PUBLISH, function* () {
			const {payload} =  yield take(actionTypes.ServerFeedback.HANDLE_SERVER_FEEDBACK)
			if (!payload.feedbackEnvelope.feedbacks.some((feedback) => feedback.type === "Neos.Neos.Ui:Success")) {
				console.warn(`CodeQ.AdvancedPublish :: Publishing doesnt seem to have been successful. Aborting.`)
				return;
			}
			yield put(actions.toggleAdvancedPublishDialog())
		})
	}

	globalRegistry.get('containers').set('Modals/CodeQ.AdvancedPublish', () => ReactDOM.createPortal(
			<ConnectedModal iframeUri={frontendConfiguration["CodeQ.AdvancedPublish"].iframeUri} />,
			document.body
	));
	globalRegistry.get('sagas').set('CodeQ.AdvancedPublish/afterPublish', { saga: afterPublish });
	globalRegistry.get('reducers').set('CodeQ.AdvancedPublish', { reducer });
});

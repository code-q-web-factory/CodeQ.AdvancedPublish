import { createAction, handleActions } from 'redux-actions';

export const actionTypes = {
  TOGGLE_ADVANCED_PUBLISH_DIALOG: 'CodeQ.AdvancedPublish/TOGGLE_ADVANCED_PUBLISH_DIALOG',
};

const toggleAdvancedPublishDialog = createAction(actionTypes.TOGGLE_ADVANCED_PUBLISH_DIALOG);

export const actions = {
  toggleAdvancedPublishDialog,
};

export const reducer = handleActions(
  {
    [actionTypes.TOGGLE_ADVANCED_PUBLISH_DIALOG]: (state, action) => ({
      ...state,
      plugins: {
        ...state.plugins,
        advancedPublishDialog: {
          open: action.payload !== undefined ? action.payload.open : !state.plugins?.advancedPublishDialog?.open,
        },
      },
    }),
  },
  {
    plugins: {
      advancedPublishDialog: {
        open: false,
      },
    },
  }
);

export const selectors = {
	advancedPublishDialogOpen: (state) => state.plugins?.advancedPublishDialog?.open,
};

/**
 * copied from the neos ui and adjusted: packages/neos-ui/src/Containers/PrimaryToolbar/PublishDropDown/index.js
 */

import React, {Fragment, PureComponent} from 'react';
import PropTypes from 'prop-types';
import {connect} from 'react-redux';
import mergeClassNames from 'classnames';

import {DropDown, Icon, Badge} from '@neos-project/react-ui-components';

import I18n from '@neos-project/neos-ui-i18n';
import {actions, selectors} from '@neos-project/neos-ui-redux-store';
import {neos} from '@neos-project/neos-ui-decorators';

const {publishableNodesSelector, publishableNodesInDocumentSelector, baseWorkspaceSelector, isWorkspaceReadOnlySelector} = selectors.CR.Workspaces;

import AbstractButton from './AbstractButton/index';
import style from './style.module.css';

@connect(state => ({
    isSaving: state?.ui?.remote?.isSaving,
    isPublishing: state?.ui?.remote?.isPublishing,
    isDiscarding: state?.ui?.remote?.isDiscarding,
    publishableNodes: publishableNodesSelector(state),
    publishableNodesInDocument: publishableNodesInDocumentSelector(state),
    baseWorkspace: baseWorkspaceSelector(state),
    isWorkspaceReadOnly: isWorkspaceReadOnlySelector(state),
}), {
    publishAction: actions.CR.Workspaces.publish,
    discardAction: actions.CR.Workspaces.commenceDiscard
})
@neos(globalRegistry => ({
    i18nRegistry: globalRegistry.get('i18n')
}))

export default class PublishDropDown extends PureComponent {
    static propTypes = {
        isSaving: PropTypes.bool,
        isPublishing: PropTypes.bool,
        isDiscarding: PropTypes.bool,
        isWorkspaceReadOnly: PropTypes.bool,
        publishableNodes: PropTypes.array,
        publishableNodesInDocument: PropTypes.array,
        baseWorkspace: PropTypes.string.isRequired,
        publishAction: PropTypes.func.isRequired,
        discardAction: PropTypes.func.isRequired,
        i18nRegistry: PropTypes.object.isRequired
    };

    handlePublishAllClick = () => {
        const {publishableNodes, publishAction, baseWorkspace} = this.props;

        publishAction(publishableNodes.map(node => node?.contextPath), baseWorkspace);
    }

    handleDiscardClick = () => {
        const {publishableNodesInDocument, discardAction} = this.props;

        discardAction(publishableNodesInDocument.map(node => node?.contextPath));
    }

    handleDiscardAllClick = () => {
        const {publishableNodes, discardAction} = this.props;

        discardAction(publishableNodes.map(node => node?.contextPath));
    }

    render() {
        const {
            publishableNodes,
            publishableNodesInDocument,
            isSaving,
            isPublishing,
            isDiscarding,
            isWorkspaceReadOnly,
            i18nRegistry,
        } = this.props;

        const canPublishLocally = !isSaving && !isPublishing && !isDiscarding && publishableNodesInDocument && (publishableNodesInDocument.length > 0);
        const canPublishGlobally = !isSaving && !isPublishing && !isDiscarding && publishableNodes && (publishableNodes.length > 0);
        const mainButton = this.getTranslatedMainButton();
        const dropDownBtnClassName = mergeClassNames({
            [style.dropDown__btn]: true,
            [style['dropDown__item--canPublish']]: canPublishGlobally,
            [style['dropDown__item--isPublishing']]: isPublishing,
            [style['dropDown__item--isSaving']]: isSaving,
            [style['dropDown__item--isDiscarding']]: isDiscarding
        });
        const publishableNodesInDocumentCount = publishableNodesInDocument ? publishableNodesInDocument.length : 0;
        const publishableNodesCount = publishableNodes ? publishableNodes.length : 0;
        return (
            <div id="neos-PublishDropDown" className={style.wrapper}>
                <AbstractButton
                    id="neos-PublishDropDown-Publish"
                    className={style.publishBtn}
                    isEnabled={!isWorkspaceReadOnly && (canPublishGlobally)}
                    isHighlighted={canPublishGlobally || isSaving || isPublishing}
                    onClick={this.handlePublishAllClick}
                    >
                    {mainButton} {isWorkspaceReadOnly ? (<Icon icon="lock"/>) : ''}
                    {publishableNodesCount > 0 && <Badge className={style.badge} label={String(publishableNodesCount)}/>}
                </AbstractButton>

                <DropDown className={style.dropDown}>
                    {isPublishing || isSaving || isDiscarding ? (
                        <DropDown.Header
                            iconIsOpen={'spinner'}
                            iconIsClosed={'spinner'}
                            iconRest={{spin: true, transform: 'up-8'}}
                            className={dropDownBtnClassName}
                            disabled
                            aria-label={i18nRegistry.translate('Neos.Neos:Main:showPublishOptions', 'Show publishing options')}
                        />
                    ) : (
                        <DropDown.Header
                            className={dropDownBtnClassName}
                            aria-label={i18nRegistry.translate('Neos.Neos:Main:showPublishOptions', 'Show publishing options')}
                        />
                    )}
                    <DropDown.Contents
                        className={style.dropDown__contents}
                        >
                        <li className={style.dropDown__item}>
                            <AbstractButton
                                id="neos-PublishDropDown-Discard"
                                isEnabled={canPublishLocally}
                                isHighlighted={false}
                                label="Discard"
                                icon="ban"
                                onClick={this.handleDiscardClick}
                                >
                                <div className={style.dropDown__iconWrapper}>
                                    <Icon icon="ban"/>
                                </div>
                                <I18n id="Neos.Neos:Main:discard" fallback="Discard"/>
                                {publishableNodesInDocumentCount > 0 && <Badge className={style.badge} label={String(publishableNodesInDocumentCount)}/>}
                            </AbstractButton>
                        </li>
                        <li className={style.dropDown__item}>
                            <AbstractButton
                                id="neos-PublishDropDown-DiscardAll"
                                isEnabled={canPublishGlobally}
                                isHighlighted={false}
                                onClick={this.handleDiscardAllClick}
                                >
                                <div className={style.dropDown__iconWrapper}>
                                    <Icon icon="ban"/>
                                </div>
                                <I18n id="Neos.Neos:Main:discardAll" fallback="Discard All"/>
                                {publishableNodesCount > 0 && <Badge className={style.badge} label={String(publishableNodesCount)}/>}
                            </AbstractButton>
                        </li>
                    </DropDown.Contents>
                </DropDown>
            </div>
        );
    }

    getTranslatedMainButton() {
        const {
						publishableNodes,
            isSaving,
            isPublishing,
            isDiscarding,
        } = this.props;
        const canPublish = publishableNodes && (publishableNodes.length > 0);

        if (isSaving) {
            return <I18n id="Neos.Neos:Main:saving" fallback="saving"/>;
        }

        if (isPublishing) {
            return <I18n id="Neos.Neos:Main:publishTo" fallback="Publish to" params={{0: "..."}}/>;
        }

        if (isDiscarding) {
            return 'Discarding...';
        }

        if (canPublish) {
						return <I18n id="Neos.Neos:Main:publishAll" fallback="Publish All"/>
        }

        return (
            <Fragment>
                <I18n id="Neos.Neos:Main:published" fallback="Published"/>
            </Fragment>
        );
    }
}

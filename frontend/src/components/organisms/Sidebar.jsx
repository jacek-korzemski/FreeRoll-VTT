import { useEffect, useState } from 'react'
import { useScrollToSection } from '../../hooks/useScrollToSection'
import { useBackgroundZoom } from '../../hooks/useBackgroundZoom'
import CollapsibleSection from '../atoms/CollapsibleSection'
import SceneManager from './SceneManager'
import SidebarHeader from '../atoms/SidebarHeader'
import FogOfWarSection from './FogOfWarSection'
import AssetBrowser from './AssetBrowser'
import ZoomControls from '../atoms/ZoomControls'
import UploadSection from './UploadSection'
import MaterialsDeleteSection from './MaterialsDeleteSection'
import TemplateManager from './TemplateManager'
import { t } from '../../lang'

const SIDEBAR_TAB_KEY = 'vtt_sidebar_tab'

function readSidebarTab() {
  try {
    return localStorage.getItem(SIDEBAR_TAB_KEY) === 'server' ? 'server' : 'game'
  } catch (_) {
    return 'game'
  }
}

function Sidebar({ 
  isOpen,
  isGameMaster = false,
  apiStatus = 'ok',
  apiFlashTrigger = 0,
  mapPath,
  mapFolders,
  mapFiles,
  mapListLoading,
  mapNavigationForwardRef,
  onMapPathChange,
  tokenPath,
  tokenFolders,
  tokenFiles,
  tokenListLoading,
  tokenNavigationForwardRef,
  onTokenPathChange,
  backgroundAssets,
  currentBackground,
  selectedAsset, 
  selectedType,
  isEraserActive,
  hasMapElements,
  isTokenEraserActive,
  hasTokens,
  isMoveToolActive,
  onToggleMoveTool,
  fogOfWar,
  fogEditMode,
  fogRevealMode,
  fogBrushSize,
  fogGmOpacity,
  onToggleFog,
  onToggleFogEdit,
  onSetFogRevealMode,
  onSetFogBrushSize,
  onSetFogGmOpacity,
  onFogRevealAll,
  onFogHideAll,
  onSelectAsset,
  onToggleEraser,
  onToggleTokenEraser,
  onRemoveBackground,
  onNudgeBackground,
  onScaleBackground,
  onResetBackgroundPosition,
  onResetBackgroundScale,
  onResetBackgroundAll,
  onToggleGridHidden,
  onClear,
  basePath,
  zoomLevel,
  onZoomChange,
  scenes,
  activeSceneId,
  sceneType = 'tactical',
  hideHp = false,
  onFocusScene,
  onShowToPlayers,
  onCreateScene,
  onDeleteScene,
  onRenameScene,
  onDuplicateScene,
  onUpdateScene,
  pingMode,
  activePing,
  onTogglePing,
  onClearPing,
  onDeselectAsset,
  onRefreshMapAssets,
  onRefreshTokenAssets,
  onRefreshBackgroundAssets,
  onRefreshPapers,
  adventureOpen = false,
  onToggleAdventure,
  onOpenAdventureWindow,
}) {
  const mapSectionRef = useScrollToSection(mapListLoading, mapNavigationForwardRef)
  const tokenSectionRef = useScrollToSection(tokenListLoading, tokenNavigationForwardRef)
  const [bgZoomStep, setBgZoomStep] = useBackgroundZoom(1)
  const [sidebarTab, setSidebarTab] = useState(readSidebarTab)

  useEffect(() => {
    try {
      localStorage.setItem(SIDEBAR_TAB_KEY, sidebarTab)
    } catch (_) {}
  }, [sidebarTab])

  const showGame = !isGameMaster || sidebarTab === 'game'
  const showServer = isGameMaster && sidebarTab === 'server'
  const type = sceneType || 'tactical'
  const showFog = type === 'tactical'
  const showMapElements = type === 'tactical' || type === 'travel' || type === 'narrative'
  const showTokens = type === 'tactical' || type === 'travel' || type === 'combat' || type === 'narrative'

  return (
    <aside className={`sidebar ${isOpen ? 'open' : 'closed'}`}>
      <SidebarHeader isGameMaster={isGameMaster} onClear={onClear} apiStatus={apiStatus} apiFlashTrigger={apiFlashTrigger} />

      <div className="sidebar-sections">
        <div className="sidebar-sections-inner">
          <div className="sidebar-sections-content">
          {isGameMaster && (
            <div className="sidebar-tabs" role="tablist">
              <button
                type="button"
                role="tab"
                aria-selected={sidebarTab === 'game'}
                className={`sidebar-tab ${sidebarTab === 'game' ? 'active' : ''}`}
                onClick={() => setSidebarTab('game')}
              >
                {t('sidebar.tabGame')}
              </button>
              <button
                type="button"
                role="tab"
                aria-selected={sidebarTab === 'server'}
                className={`sidebar-tab ${sidebarTab === 'server' ? 'active' : ''}`}
                onClick={() => setSidebarTab('server')}
              >
                {t('sidebar.tabServer')}
              </button>
            </div>
          )}
          {showGame && isGameMaster && (
            <section className={`sidebar-section collapsible adventure-launch${adventureOpen ? ' open' : ''}`}>
              <h2>
                <button
                  type="button"
                  className="adventure-launch-open"
                  onClick={onToggleAdventure}
                >
                  <span className="section-icon" aria-hidden>📜</span>
                  <span className="section-title">{t('adventure.open')}</span>
                </button>
                <button
                  type="button"
                  className="adventure-launch-window"
                  onClick={onOpenAdventureWindow}
                  title={t('adventure.openWindow')}
                  aria-label={t('adventure.openWindow')}
                >
                  ↗
                </button>
              </h2>
            </section>
          )}
          {showServer && (
              <CollapsibleSection 
                title={t('upload.title')} 
                icon="📁" 
                defaultOpen={false}
                onToggle={onDeselectAsset}
              >
                <UploadSection
                  onUploadedImages={() => {
                    onRefreshMapAssets && onRefreshMapAssets()
                    onRefreshTokenAssets && onRefreshTokenAssets()
                  }}
                  onUploadedBackgrounds={onRefreshBackgroundAssets}
                  onUploadedTemplates={null}
                  onUploadedPapers={onRefreshPapers}
                />
                <MaterialsDeleteSection
                  onDeleted={(typeDeleted) => {
                    if (typeDeleted === 'token') onRefreshTokenAssets?.()
                    else if (typeDeleted === 'map') onRefreshMapAssets?.()
                    else if (typeDeleted === 'background') onRefreshBackgroundAssets?.()
                    else if (typeDeleted === 'paper') onRefreshPapers?.()
                  }}
                />
              </CollapsibleSection>
            )}

            
            {showServer && (
              <CollapsibleSection
                title={t('templates.title')}
                icon="📄"
                defaultOpen={false}
                onToggle={onDeselectAsset}
              >
                <TemplateManager />
              </CollapsibleSection>
            )}

            {showGame && isGameMaster && (
              <CollapsibleSection 
                title={t('scenes.title')} 
                icon="🎬" 
                defaultOpen={false}
                badge={scenes.length}
                onToggle={onDeselectAsset}
              >
                <SceneManager
                  scenes={scenes}
                  activeSceneId={activeSceneId}
                  hideHp={hideHp}
                  onFocusScene={onFocusScene}
                  onShowToPlayers={onShowToPlayers}
                  onCreateScene={onCreateScene}
                  onDeleteScene={onDeleteScene}
                  onRenameScene={onRenameScene}
                  onDuplicateScene={onDuplicateScene}
                  onUpdateScene={onUpdateScene}
                  backgroundAssets={backgroundAssets}
                  currentBackground={currentBackground}
                  bgZoomStep={bgZoomStep}
                  onBgZoomStepChange={setBgZoomStep}
                  onRemoveBackground={onRemoveBackground}
                  onNudgeBackground={onNudgeBackground}
                  onScaleBackground={onScaleBackground}
                  onResetBackgroundPosition={onResetBackgroundPosition}
                  onResetBackgroundScale={onResetBackgroundScale}
                  onResetBackgroundAll={onResetBackgroundAll}
                  onToggleGridHidden={onToggleGridHidden}
                />
              </CollapsibleSection>
            )}

            {showGame && isGameMaster && showFog && (
              <FogOfWarSection
                key="fog"
                fogOfWar={fogOfWar}
                fogEditMode={fogEditMode}
                fogRevealMode={fogRevealMode}
                fogBrushSize={fogBrushSize}
                fogGmOpacity={fogGmOpacity}
                isGameMaster={isGameMaster}
                onToggleFog={onToggleFog}
                onToggleFogEdit={onToggleFogEdit}
                onSetFogRevealMode={onSetFogRevealMode}
                onSetFogBrushSize={onSetFogBrushSize}
                onSetFogGmOpacity={onSetFogGmOpacity}
                onFogRevealAll={onFogRevealAll}
                onFogHideAll={onFogHideAll}
                onDeselectAsset={onDeselectAsset}
              />
            )}

            {showGame && showMapElements && (
            <CollapsibleSection 
              key="map-elements"
              title={t('sidebar.mapElements')} 
              icon="🏠" 
              defaultOpen={false}
              badge={mapFolders.length + mapFiles.length || null}
              onToggle={onDeselectAsset}
            >
              <AssetBrowser
                path={mapPath}
                folders={mapFolders}
                files={mapFiles}
                isLoading={mapListLoading}
                sectionRef={mapSectionRef}
                selectedAsset={selectedAsset}
                selectedType={selectedType}
                basePath={basePath}
                onPathChange={onMapPathChange}
                onSelectAsset={onSelectAsset}
                assetType="map"
                rootIcon="🏠"
                rootTitle={t('sidebar.mapElementsRoot')}
                pingMode={pingMode}
                activePing={activePing}
                onTogglePing={onTogglePing}
                onClearPing={onClearPing}
                isEraserActive={isEraserActive}
                hasMapElements={hasMapElements}
                onToggleEraser={onToggleEraser}
                isMoveToolActive={isMoveToolActive}
                onToggleMoveTool={onToggleMoveTool}
              />
            </CollapsibleSection>
            )}

            {showGame && showTokens && (
            <CollapsibleSection 
              key="tokens"
              title={t('sidebar.tokens')} 
              icon="🎭" 
              defaultOpen={false}
              badge={tokenFolders.length + tokenFiles.length || null}
              onToggle={onDeselectAsset}
            >
              <AssetBrowser
                path={tokenPath}
                folders={tokenFolders}
                files={tokenFiles}
                isLoading={tokenListLoading}
                sectionRef={tokenSectionRef}
                selectedAsset={selectedAsset}
                selectedType={selectedType}
                basePath={basePath}
                onPathChange={onTokenPathChange}
                onSelectAsset={onSelectAsset}
                assetType="token"
                rootIcon="🎭"
                rootTitle={t('sidebar.tokensRoot')}
                isTokenEraserActive={isTokenEraserActive}
                hasTokens={hasTokens}
                onToggleTokenEraser={onToggleTokenEraser}
              />
            </CollapsibleSection>
            )}
          </div>

          <ZoomControls zoomLevel={zoomLevel} onZoomChange={onZoomChange} />
        </div>
      </div>
    </aside>
  )
}

export default Sidebar

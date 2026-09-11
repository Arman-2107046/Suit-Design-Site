import React, { useState, useEffect, useMemo, useCallback, useRef } from "react";
import { Loader2, Layers, Scissors, Palette, Menu, X, AlertTriangle } from "lucide-react";

const DEBUG_DIAGRAMS = false;

/* ------------------------------------------------------------------ */
/*  Smart Image Cache with Lazy Loading                                */
/* ------------------------------------------------------------------ */
class SmartImageCache {
    constructor() {
        this.memoryCache = new Map();
        this.loadingPromises = new Map();
        this.listeners = new Set();
    }

    subscribe(listener) {
        this.listeners.add(listener);
        return () => this.listeners.delete(listener);
    }

    notifyListeners(loadingCount) {
        this.listeners.forEach(listener => listener(loadingCount));
    }

    async preloadImage(url) {
        if (!url) return null;

        if (this.memoryCache.has(url)) {
            return this.memoryCache.get(url);
        }

        if (this.loadingPromises.has(url)) {
            return this.loadingPromises.get(url);
        }

        const loadPromise = new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                this.memoryCache.set(url, img);
                this.loadingPromises.delete(url);
                this.notifyListeners(this.loadingPromises.size);
                resolve(img);
            };
            img.onerror = () => {
                console.warn(`Failed to load image: ${url}`);
                this.loadingPromises.delete(url);
                this.notifyListeners(this.loadingPromises.size);
                resolve(null);
            };
            img.src = url;
        });

        this.loadingPromises.set(url, loadPromise);
        this.notifyListeners(this.loadingPromises.size);
        return loadPromise;
    }

    async preloadBatch(urls, onProgress) {
        const uniqueUrls = [...new Set(urls.filter(Boolean))];
        const totalImages = uniqueUrls.length;
        let loadedCount = 0;

        const uncachedUrls = uniqueUrls.filter(url => !this.memoryCache.has(url));

        const batchSize = 6;
        for (let i = 0; i < uncachedUrls.length; i += batchSize) {
            const batch = uncachedUrls.slice(i, i + batchSize);
            await Promise.all(batch.map(url => this.preloadImage(url)));

            loadedCount += batch.length;
            const progress = (loadedCount / totalImages) * 100;
            if (onProgress) onProgress(progress, loadedCount, totalImages);
        }

        return uniqueUrls;
    }

    isImageCached(url) {
        return this.memoryCache.has(url);
    }

    getLoadingCount() {
        return this.loadingPromises.size;
    }

    areAllCached(urls) {
        if (!urls || urls.length === 0) return true;
        return urls.every(url => !url || this.memoryCache.has(url));
    }
}

const smartImageCache = new SmartImageCache();

/* ------------------------------------------------------------------ */
/*  Loading indicator component                                        */
/* ------------------------------------------------------------------ */
const LoadingIndicator = ({ loadingCount }) => {
    if (loadingCount === 0) return null;

    return (
        <div className="absolute top-4 right-4 z-50 flex items-center gap-2 px-3 py-1.5 bg-white/90 backdrop-blur-sm rounded-full shadow-lg animate-in fade-in slide-in-from-top-2">
            <Loader2 className="w-3.5 h-3.5 text-gray-900 animate-spin" />
            <span className="text-xs font-medium text-gray-700">
                Loading {loadingCount} image{loadingCount > 1 ? 's' : ''}...
            </span>
        </div>
    );
};

/* ------------------------------------------------------------------ */
/*  Canvas layer - shows cached image without spinner                 */
/* ------------------------------------------------------------------ */
const CanvasLayer = ({ layer }) => {
    const [displayedImage, setDisplayedImage] = useState(null);
    const [isImageLoading, setIsImageLoading] = useState(false);

    useEffect(() => {
        let isMounted = true;

        const loadImage = async () => {
            if (!layer.image) {
                setDisplayedImage(null);
                setIsImageLoading(false);
                return;
            }

            if (displayedImage === layer.image) return;

            if (smartImageCache.isImageCached(layer.image)) {
                const cachedImg = smartImageCache.memoryCache.get(layer.image);
                if (isMounted) {
                    setDisplayedImage(layer.image);
                    setIsImageLoading(false);
                }
                return;
            }

            setIsImageLoading(true);

            const img = await smartImageCache.preloadImage(layer.image);
            if (isMounted && img) {
                setDisplayedImage(layer.image);
                setIsImageLoading(false);
            }
        };

        loadImage();

        return () => {
            isMounted = false;
        };
    }, [layer.image]);

    const showSpinner = isImageLoading;

    return (
        <div
            className="absolute inset-0 flex items-center justify-center overflow-hidden pointer-events-none"
            style={{ zIndex: layer.layerIndex }}
        >
            <div className="relative flex items-center justify-center w-full h-full">
                {displayedImage && (
                    <img
                        src={displayedImage}
                        alt={layer.type}
                        className="absolute inset-0 object-contain w-full h-full"
                        style={{ opacity: 1 }}
                        onError={(e) => {
                            console.error(`Failed to load ${layer.type}:`, layer.image);
                            e.target.style.display = "none";
                        }}
                    />
                )}

                {showSpinner && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center">
                        <div className="flex flex-col items-center gap-3">
                            <span className="loader"></span>
                            <span className="text-xs font-medium text-gray-500 animate-pulse">Loading...</span>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

/* ------------------------------------------------------------------ */
/*  Enhanced Selection Hook with loading state                         */
/* ------------------------------------------------------------------ */
const useSelectionWithLoading = (initialValue = null) => {
    const [selected, setSelected] = useState(initialValue);
    const [pendingSelection, setPendingSelection] = useState(null);
    const [isLoading, setIsLoading] = useState(false);
    const previousSelectionRef = useRef(initialValue);

    const selectWithLoading = useCallback(async (newSelection, loadImageFn) => {
        if (!newSelection) {
            previousSelectionRef.current = selected;
            setSelected(newSelection);
            return;
        }

        const imageUrl = loadImageFn ? loadImageFn(newSelection) : newSelection.image;

        if (imageUrl && smartImageCache.isImageCached(imageUrl)) {
            previousSelectionRef.current = selected;
            setSelected(newSelection);
            return;
        }

        setIsLoading(true);
        setPendingSelection(newSelection);

        try {
            if (imageUrl) {
                await smartImageCache.preloadImage(imageUrl);
            }

            previousSelectionRef.current = selected;
            setSelected(newSelection);
            setPendingSelection(null);
            setIsLoading(false);
        } catch (error) {
            console.error('Failed to load selection:', error);
            setPendingSelection(null);
            setIsLoading(false);
        }
    }, [selected]);

    return {
        selected,
        pendingSelection,
        isLoading,
        previousSelection: previousSelectionRef.current,
        selectWithLoading,
        setSelected
    };
};

const SuitDesigner = () => {
    const [suitData, setSuitData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [activeLoads, setActiveLoads] = useState(0);
    const [isViewReady, setIsViewReady] = useState(false);
    const viewLoadTimeoutRef = useRef(null);
    const [currentLayerUrls, setCurrentLayerUrls] = useState([]);

    const {
        selected: selectedFabric,
        pendingSelection: pendingFabric,
        isLoading: isFabricLoading,
        selectWithLoading: selectFabricWithLoading,
        setSelected: setSelectedFabric
    } = useSelectionWithLoading(null);

    const {
        selected: selectedBody,
        pendingSelection: pendingBody,
        isLoading: isBodyLoading,
        selectWithLoading: selectBodyWithLoading,
        setSelected: setSelectedBody
    } = useSelectionWithLoading(null);

    const {
        selected: selectedSleeve,
        pendingSelection: pendingSleeve,
        isLoading: isSleeveLoading,
        selectWithLoading: selectSleeveWithLoading,
        setSelected: setSelectedSleeve
    } = useSelectionWithLoading(null);

    const {
        selected: selectedLapelCategory,
        pendingSelection: pendingLapelCategory,
        isLoading: isLapelCategoryLoading,
        selectWithLoading: selectLapelCategoryWithLoading,
        setSelected: setSelectedLapelCategory
    } = useSelectionWithLoading(null);

    const {
        selected: selectedLapel,
        pendingSelection: pendingLapel,
        isLoading: isLapelLoading,
        selectWithLoading: selectLapelWithLoading,
        setSelected: setSelectedLapel
    } = useSelectionWithLoading(null);

    const {
        selected: selectedSidePocket,
        pendingSelection: pendingSidePocket,
        isLoading: isSidePocketLoading,
        selectWithLoading: selectSidePocketWithLoading,
        setSelected: setSelectedSidePocket
    } = useSelectionWithLoading(null);

    const {
        selected: selectedChestPocket,
        pendingSelection: pendingChestPocket,
        isLoading: isChestPocketLoading,
        selectWithLoading: selectChestPocketWithLoading,
        setSelected: setSelectedChestPocket
    } = useSelectionWithLoading(null);

    const {
        selected: selectedLining,
        pendingSelection: pendingLining,
        isLoading: isLiningLoading,
        selectWithLoading: selectLiningWithLoading,
        setSelected: setSelectedLining
    } = useSelectionWithLoading(null);

    const {
        selected: selectedButton,
        pendingSelection: pendingButton,
        isLoading: isButtonLoading,
        selectWithLoading: selectButtonWithLoading,
        setSelected: setSelectedButton
    } = useSelectionWithLoading(null);

    const [liningMode, setLiningMode] = useState("default");
    const [showLiningSidebar, setShowLiningSidebar] = useState(false);
    const [activeTab, setActiveTab] = useState("fabric");

    // Store per-fabric exact selections (IDs)
    const [fabricConfigs, setFabricConfigs] = useState({});

    useEffect(() => {
        fetchSuitData();
    }, []);

    const fetchSuitData = async () => {
        try {
            setLoading(true);
            const response = await fetch("/api/configurator");

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            setSuitData(data);

            if (data.success && data.data && data.data.length > 0) {
                const defaultFabric = data.data.find(f => f.is_default) || data.data[0];
                setSelectedFabric(defaultFabric);
                initializeFabricDefaults(defaultFabric, null);
            }

            setLoading(false);
        } catch (err) {
            console.error("Error fetching suit data:", err);
            setError(err.message);
            setLoading(false);
        }
    };

    // ---------- PERSISTENCE HELPERS ----------
    const findMatchingOrFallback = useCallback((items, targetId, fallbackToDefault = true) => {
        if (!items || items.length === 0) return null;

        if (targetId) {
            const match = items.find(item => item.id === targetId);
            if (match) return match;
        }

        if (fallbackToDefault) {
            const defaultItem = items.find(item => item.is_default === true);
            if (defaultItem) return defaultItem;
        }

        return items[0];
    }, []);

    const findMatchingLapelCategory = useCallback((lapels, targetCategoryId) => {
        if (!lapels || lapels.length === 0) return null;

        if (targetCategoryId) {
            const match = lapels.find(l => l.category?.id === targetCategoryId);
            if (match) return match.category;
        }

        const defaultLapel = lapels.find(l => l.category?.is_default === true) || lapels[0];
        return defaultLapel?.category || null;
    }, []);

    const findMatchingLapel = useCallback((lapels, targetCategoryId, targetLapelId) => {
        if (!lapels || lapels.length === 0) return null;

        if (targetLapelId) {
            const match = lapels.find(l => l.id === targetLapelId);
            if (match) return match;
        }

        if (targetCategoryId) {
            const categoryLapels = lapels.filter(l => l.category?.id === targetCategoryId);
            if (categoryLapels.length > 0) {
                const defaultSub = categoryLapels.find(l => l.subcategory?.is_default === true || l.is_default === true) || categoryLapels[0];
                return defaultSub;
            }
        }

        const defaultLapel = lapels.find(l => l.subcategory?.is_default === true || l.is_default === true) || lapels[0];
        return defaultLapel;
    }, []);

    const findMatchingButton = useCallback((buttons, targetButtonId) => {
        if (!buttons || buttons.length === 0) return null;

        if (targetButtonId) {
            const match = buttons.find(b => b.id === targetButtonId);
            if (match) return match;
        }

        return null;
    }, []);

    // Semantic matching helpers
    const findBodyByCode = useCallback((bodies, code) => {
        if (!bodies || bodies.length === 0) return null;
        return bodies.find(b => b.body_type?.code === code) || bodies.find(b => b.is_default) || bodies[0];
    }, []);

    const findSleeveByCode = useCallback((sleeves, code) => {
        if (!sleeves || sleeves.length === 0) return null;
        return sleeves.find(s => s.type?.code === code) || sleeves.find(s => s.is_default) || sleeves[0];
    }, []);

    const findSidePocketByCode = useCallback((pockets, code) => {
        if (!pockets || pockets.length === 0) return null;
        return pockets.find(p => p.type?.code === code) || pockets.find(p => p.is_default) || pockets[0];
    }, []);

    const findChestPocketByCode = useCallback((pockets, code) => {
        if (!pockets || pockets.length === 0) return null;
        return pockets.find(p => p.type?.code === code) || pockets.find(p => p.is_default) || pockets[0];
    }, []);

    const findLapelCategoryById = useCallback((lapels, categoryId) => {
        if (!lapels || lapels.length === 0) return null;
        const match = lapels.find(l => l.category?.id === categoryId);
        if (match) return match.category;
        const defaultLapel = lapels.find(l => l.category?.is_default === true) || lapels[0];
        return defaultLapel?.category || null;
    }, []);

    const findLapelByCategoryAndSubcategory = useCallback((lapels, categoryId, subcategoryId) => {
        if (!lapels || lapels.length === 0) return null;
        if (categoryId && subcategoryId) {
            const match = lapels.find(l => l.category?.id === categoryId && l.subcategory?.id === subcategoryId);
            if (match) return match;
        }
        if (categoryId) {
            const categoryLapels = lapels.filter(l => l.category?.id === categoryId);
            if (categoryLapels.length > 0) {
                return categoryLapels.find(l => l.subcategory?.is_default === true || l.is_default === true) || categoryLapels[0];
            }
        }
        return lapels.find(l => l.subcategory?.is_default === true || l.is_default === true) || lapels[0];
    }, []);

    const findButtonByImageId = useCallback((buttons, imageId) => {
        if (!buttons || buttons.length === 0) return null;
        return buttons.find(b => b.button_image?.id === imageId) || null;
    }, []);

    const saveCurrentConfig = useCallback((fabricId) => {
        const config = {
            bodyId: selectedBody?.id || null,
            sleeveId: selectedSleeve?.id || null,
            lapelCategoryId: selectedLapelCategory?.id || null,
            lapelId: selectedLapel?.id || null,
            sidePocketId: selectedSidePocket?.id || null,
            chestPocketId: selectedChestPocket?.id || null,
            buttonId: selectedButton?.id || null,
        };
        setFabricConfigs(prev => ({
            ...prev,
            [fabricId]: config,
        }));
    }, [selectedBody, selectedSleeve, selectedLapelCategory, selectedLapel, selectedSidePocket, selectedChestPocket, selectedButton]);

    const applyExactConfig = useCallback((fabric, config) => {
        const newBody = findMatchingOrFallback(fabric.bodies, config.bodyId, true);
        if (newBody) {
            setSelectedBody(newBody);

            const newLapelCategory = findMatchingLapelCategory(newBody.lapels, config.lapelCategoryId);
            if (newLapelCategory) {
                setSelectedLapelCategory(newLapelCategory);
                const newLapel = findMatchingLapel(newBody.lapels, newLapelCategory.id, config.lapelId);
                if (newLapel) {
                    setSelectedLapel(newLapel);
                } else {
                    setSelectedLapel(null);
                }
            } else {
                setSelectedLapelCategory(null);
                setSelectedLapel(null);
            }
        } else {
            setSelectedBody(null);
            setSelectedLapelCategory(null);
            setSelectedLapel(null);
        }

        const newSleeve = findMatchingOrFallback(fabric.sleeves, config.sleeveId, true);
        setSelectedSleeve(newSleeve);

        const newSidePocket = findMatchingOrFallback(fabric.side_pockets, config.sidePocketId, true);
        setSelectedSidePocket(newSidePocket);

        const newChestPocket = findMatchingOrFallback(fabric.chest_pockets, config.chestPocketId, true);
        setSelectedChestPocket(newChestPocket);

        if (newBody?.body_type?.body_buttons) {
            const newButton = findMatchingButton(newBody.body_type.body_buttons, config.buttonId);
            setSelectedButton(newButton);
        } else {
            setSelectedButton(null);
        }
    }, [findMatchingOrFallback, findMatchingLapelCategory, findMatchingLapel, findMatchingButton]);

    const applySemanticConfig = useCallback((fabric, semanticConfig) => {
        const newBody = findBodyByCode(fabric.bodies, semanticConfig.body_type_code);
        if (newBody) {
            setSelectedBody(newBody);

            const newLapelCategory = findLapelCategoryById(newBody.lapels, semanticConfig.lapel_category_id);
            if (newLapelCategory) {
                setSelectedLapelCategory(newLapelCategory);
                const newLapel = findLapelByCategoryAndSubcategory(newBody.lapels, newLapelCategory.id, semanticConfig.lapel_subcategory_id);
                setSelectedLapel(newLapel);
            } else {
                setSelectedLapelCategory(null);
                setSelectedLapel(null);
            }
        } else {
            setSelectedBody(null);
            setSelectedLapelCategory(null);
            setSelectedLapel(null);
        }

        const newSleeve = findSleeveByCode(fabric.sleeves, semanticConfig.sleeve_type_code);
        setSelectedSleeve(newSleeve);

        const newSidePocket = findSidePocketByCode(fabric.side_pockets, semanticConfig.side_pocket_type_code);
        setSelectedSidePocket(newSidePocket);

        const newChestPocket = findChestPocketByCode(fabric.chest_pockets, semanticConfig.chest_pocket_type_code);
        setSelectedChestPocket(newChestPocket);

        if (newBody?.body_type?.body_buttons) {
            const newButton = findButtonByImageId(newBody.body_type.body_buttons, semanticConfig.button_image_id);
            setSelectedButton(newButton);
        } else {
            setSelectedButton(null);
        }
    }, [findBodyByCode, findSleeveByCode, findSidePocketByCode, findChestPocketByCode, findLapelCategoryById, findLapelByCategoryAndSubcategory, findButtonByImageId]);

    // ---------- FABRIC CHANGE ----------
    const handleFabricChange = async (fabric) => {
        if (!fabric) return;

        // Save current fabric's exact config before switching
        if (selectedFabric?.id) {
            saveCurrentConfig(selectedFabric.id);
        }

        // Capture the semantic configuration from the CURRENT selections (before they change)
        const currentSemanticConfig = {
            body_type_code: selectedBody?.body_type?.code || null,
            sleeve_type_code: selectedSleeve?.type?.code || null,
            side_pocket_type_code: selectedSidePocket?.type?.code || null,
            chest_pocket_type_code: selectedChestPocket?.type?.code || null,
            lapel_category_id: selectedLapelCategory?.id || null,
            lapel_subcategory_id: selectedLapel?.subcategory?.id || null,
            button_image_id: selectedButton?.button_image?.id || null,
        };

        // Set the new fabric
        setSelectedFabric(fabric);

        // Reset lining
        setSelectedLining(null);
        setLiningMode("default");
        setShowLiningSidebar(false);

        // Check if we already have an exact config for this fabric
        const savedConfig = fabricConfigs[fabric.id];
        if (savedConfig) {
            applyExactConfig(fabric, savedConfig);
        } else {
            // Use the semantic config to find matching components in the new fabric
            applySemanticConfig(fabric, currentSemanticConfig);
        }
    };

    // ---------- COMPONENT CHANGE HANDLERS ----------
    const handleBodyChange = async (body) => {
        await selectBodyWithLoading(body, (b) => b.image || b.body_type?.diagram);

        setSelectedButton(null);

        if (body.lapels && body.lapels.length > 0) {
            const defaultLapel = body.lapels.find((l) =>
                l.subcategory?.is_default === true || l.is_default === true
            ) || body.lapels.find((l) =>
                l.category?.is_default === true
            ) || body.lapels[0];

            await selectLapelWithLoading(defaultLapel, (l) => l.image);
            await selectLapelCategoryWithLoading(defaultLapel.category, (c) => c?.diagram);
        } else {
            setSelectedLapelCategory(null);
            setSelectedLapel(null);
        }
    };

    const handleLapelCategoryChange = async (category) => {
        await selectLapelCategoryWithLoading(category, (c) => c?.diagram);

        const categoryLapels = selectedBody?.lapels?.filter((l) => l.category?.id === category.id);
        if (categoryLapels && categoryLapels.length > 0) {
            const defaultSub = categoryLapels.find((l) =>
                l.subcategory?.is_default === true || l.is_default === true
            ) || categoryLapels[0];

            await selectLapelWithLoading(defaultSub, (l) => l.subcategory?.diagram || l.image);
        }
    };

    const handleLapelSelect = async (lapel) => {
        await selectLapelWithLoading(lapel, (l) => l.subcategory?.diagram || l.image);
    };

    const handleSleeveSelect = async (sleeve) => {
        await selectSleeveWithLoading(sleeve, (s) => s.type?.diagram || s.image);
    };

    const handleSidePocketSelect = async (pocket) => {
        await selectSidePocketWithLoading(pocket, (p) => p.type?.diagram || p.image);
    };

    const handleChestPocketSelect = async (pocket) => {
        await selectChestPocketWithLoading(pocket, (p) => p.type?.diagram || p.image);
    };

    const handleButtonSelect = async (button) => {
        await selectButtonWithLoading(button, (b) => b.button_image?.diagram || b.image);
    };

    const handleDefaultButtonClick = () => {
        setSelectedButton(null);
    };

    const handleTabChange = (tabId) => {
        setActiveTab(tabId);
    };

    const getLapelCategories = (lapels) => {
        if (!lapels || lapels.length === 0) return [];
        const categories = {};
        lapels.forEach((lapel) => {
            const categoryId = lapel.category?.id;
            if (categoryId && !categories[categoryId]) {
                categories[categoryId] = {
                    id: categoryId,
                    name: lapel.category?.name,
                    diagram: lapel.category?.diagram,
                    is_default: lapel.category?.is_default || false,
                    subcategories: [],
                };
            }
            if (categoryId) categories[categoryId].subcategories.push(lapel);
        });
        return Object.values(categories);
    };

    const initializeFabricDefaults = useCallback((fabric, persistedConfig) => {
        setSelectedBody(null);
        setSelectedSleeve(null);
        setSelectedLapelCategory(null);
        setSelectedLapel(null);
        setSelectedSidePocket(null);
        setSelectedChestPocket(null);
        setSelectedLining(null);
        setSelectedButton(null);
        setLiningMode("default");
        setShowLiningSidebar(false);

        const config = persistedConfig || {};

        if (fabric.bodies && fabric.bodies.length > 0) {
            let defaultBody;
            if (config.bodyId) {
                defaultBody = fabric.bodies.find((b) => b.id === config.bodyId) || fabric.bodies.find((b) => b.is_default) || fabric.bodies[0];
            } else {
                defaultBody = fabric.bodies.find((b) => b.is_default) || fabric.bodies[0];
            }
            setSelectedBody(defaultBody);

            if (defaultBody.lapels && defaultBody.lapels.length > 0) {
                let defaultLapel;
                if (config.lapelId) {
                    defaultLapel = defaultBody.lapels.find((l) => l.id === config.lapelId);
                }
                if (!defaultLapel && config.lapelCategoryId) {
                    defaultLapel = defaultBody.lapels.find((l) => l.category?.id === config.lapelCategoryId && l.is_default);
                }
                if (!defaultLapel) {
                    defaultLapel = defaultBody.lapels.find((l) =>
                        l.subcategory?.is_default === true || l.is_default === true
                    ) || defaultBody.lapels.find((l) =>
                        l.category?.is_default === true
                    ) || defaultBody.lapels[0];
                }

                setSelectedLapel(defaultLapel);
                setSelectedLapelCategory(defaultLapel?.category || null);
            }
        }

        if (fabric.sleeves && fabric.sleeves.length > 0) {
            let defaultSleeve;
            if (config.sleeveId) {
                defaultSleeve = fabric.sleeves.find((s) => s.id === config.sleeveId) || fabric.sleeves.find((s) => s.is_default) || fabric.sleeves[0];
            } else {
                defaultSleeve = fabric.sleeves.find((s) => s.is_default) || fabric.sleeves[0];
            }
            setSelectedSleeve(defaultSleeve);
        }

        if (fabric.side_pockets && fabric.side_pockets.length > 0) {
            let defaultPocket;
            if (config.sidePocketId) {
                defaultPocket = fabric.side_pockets.find((p) => p.id === config.sidePocketId) || fabric.side_pockets.find((p) => p.is_default) || fabric.side_pockets[0];
            } else {
                defaultPocket = fabric.side_pockets.find((p) => p.is_default) || fabric.side_pockets[0];
            }
            setSelectedSidePocket(defaultPocket);
        }

        if (fabric.chest_pockets && fabric.chest_pockets.length > 0) {
            let defaultPocket;
            if (config.chestPocketId) {
                defaultPocket = fabric.chest_pockets.find((p) => p.id === config.chestPocketId) || fabric.chest_pockets.find((p) => p.is_default) || fabric.chest_pockets[0];
            } else {
                defaultPocket = fabric.chest_pockets.find((p) => p.is_default) || fabric.chest_pockets[0];
            }
            setSelectedChestPocket(defaultPocket);
        }
    }, []);

    const handleDefaultLiningClick = () => {
        setLiningMode("default");
        setSelectedLining(null);
        setShowLiningSidebar(false);
    };

    const handleCustomLiningClick = () => {
        setLiningMode("custom");
        setShowLiningSidebar(true);

        if (selectedFabric) {
            const urls = new Set();
            (selectedFabric.custom_linings || []).forEach(lining => {
                if (lining.image) urls.add(lining.image);
                if (lining.fabric?.image) urls.add(lining.fabric.image);
                if (lining.type?.diagram) urls.add(lining.type.diagram);
            });
            smartImageCache.preloadBatch([...urls]);
        }
    };

    const handleLiningSelect = async (lining) => {
        await selectLiningWithLoading(lining, (l) => l.fabric?.image || l.image);
        setLiningMode("custom");
        setShowLiningSidebar(false);
    };

    // --- LAYERS: uses only selected values (no pending) ---
    const layers = useMemo(() => {
        const allLayers = [];

        // Use only selected, not pending
        const effectiveLining = selectedLining;
        const effectiveBody = selectedBody;
        const effectiveSleeve = selectedSleeve;
        const effectiveLapel = selectedLapel;
        const effectiveSidePocket = selectedSidePocket;
        const effectiveChestPocket = selectedChestPocket;
        const effectiveButton = selectedButton;

        if (effectiveBody?.default_linings && effectiveBody.default_linings.length > 0) {
            const defaultLining = effectiveBody.default_linings[0];
            allLayers.push({
                id: `default-lining-${defaultLining.id}`,
                type: "defaultLining",
                image: defaultLining.image,
                layerIndex: defaultLining.layer_index || 0,
            });
        }

        if (effectiveLining) {
            allLayers.push({
                id: `lining-${effectiveLining.id}`,
                type: "lining",
                image: effectiveLining.image,
                layerIndex: effectiveLining.layer_index || 100,
            });
        }

        if (effectiveBody) {
            allLayers.push({
                id: `body-${effectiveBody.id}`,
                type: "body",
                image: effectiveBody.image,
                layerIndex: effectiveBody.layer_index || 100,
            });
        }

        if (effectiveSleeve) {
            allLayers.push({
                id: `sleeve-${effectiveSleeve.id}`,
                type: "sleeve",
                image: effectiveSleeve.image,
                layerIndex: effectiveSleeve.layer_index || 150,
            });
        }

        if (effectiveLapel) {
            allLayers.push({
                id: `lapel-${effectiveLapel.id}`,
                type: "lapel",
                image: effectiveLapel.image,
                layerIndex: effectiveLapel.layer_index || 150,
            });
        }

        if (effectiveSidePocket) {
            allLayers.push({
                id: `sidePocket-${effectiveSidePocket.id}`,
                type: "sidePocket",
                image: effectiveSidePocket.image,
                layerIndex: effectiveSidePocket.layer_index || 100,
            });
        }

        if (effectiveChestPocket) {
            allLayers.push({
                id: `chestPocket-${effectiveChestPocket.id}`,
                type: "chestPocket",
                image: effectiveChestPocket.image,
                layerIndex: effectiveChestPocket.layer_index || 100,
            });
        }

        if (effectiveButton) {
            allLayers.push({
                id: `button-${effectiveButton.id}`,
                type: "button",
                image: effectiveButton.image,
                layerIndex: effectiveButton.layer_index || 160,
            });
        }

        return allLayers.sort((a, b) => a.layerIndex - b.layerIndex);
    }, [
        selectedLining, selectedBody, selectedSleeve, selectedLapel,
        selectedSidePocket, selectedChestPocket, selectedButton
        // No pending states here
    ]);

    // --- Determine if any component is loading ---
    const isAnyLoading = useMemo(() => {
        return isFabricLoading || isBodyLoading || isSleeveLoading ||
               isLapelCategoryLoading || isLapelLoading || isSidePocketLoading ||
               isChestPocketLoading || isButtonLoading || isLiningLoading;
    }, [
        isFabricLoading, isBodyLoading, isSleeveLoading,
        isLapelCategoryLoading, isLapelLoading, isSidePocketLoading,
        isChestPocketLoading, isButtonLoading, isLiningLoading
    ]);

    // --- Preload all layers whenever they change ---
    useEffect(() => {
        if (viewLoadTimeoutRef.current) {
            clearTimeout(viewLoadTimeoutRef.current);
            viewLoadTimeoutRef.current = null;
        }

        setIsViewReady(false);

        const hasImages = layers.some(layer => layer.image);
        if (!hasImages) {
            setIsViewReady(true);
            return;
        }

        const urls = layers.map(layer => layer.image).filter(Boolean);
        setCurrentLayerUrls(urls);

        if (smartImageCache.areAllCached(urls)) {
            setIsViewReady(true);
            return;
        }

        const preloadAll = async () => {
            try {
                await smartImageCache.preloadBatch(urls);

                if (smartImageCache.areAllCached(urls)) {
                    setIsViewReady(true);
                }
            } catch (error) {
                console.error('Error preloading view:', error);
                viewLoadTimeoutRef.current = setTimeout(() => {
                    setIsViewReady(true);
                }, 3000);
            }
        };

        preloadAll();

        viewLoadTimeoutRef.current = setTimeout(() => {
            setIsViewReady(true);
        }, 5000);

        return () => {
            if (viewLoadTimeoutRef.current) {
                clearTimeout(viewLoadTimeoutRef.current);
                viewLoadTimeoutRef.current = null;
            }
        };
    }, [layers]);

    useEffect(() => {
        const unsubscribe = smartImageCache.subscribe((loadingCount) => {
            setActiveLoads(loadingCount);

            if (loadingCount === 0 && currentLayerUrls.length > 0) {
                setIsViewReady(true);
            }
        });

        return () => {
            if (unsubscribe) unsubscribe();
        };
    }, [currentLayerUrls]);

    // ---------- UI COMPONENTS ----------
    const FabricOptionTile = ({ isSelected, onClick, image, label, price, isLoading }) => {
        const [cachedImage, setCachedImage] = useState(null);
        const [failed, setFailed] = useState(false);

        useEffect(() => {
            if (image) {
                smartImageCache.preloadImage(image).then(img => {
                    if (img) setCachedImage(img.src);
                });
            }
        }, [image]);

        return (
            <button
                onClick={onClick}
                className={`relative p-3 rounded-lg transition-all duration-300 ease-out ${
                    isSelected
                        ? " shadow-md scale-[1.02]"
                        : " hover:shadow-md hover:scale-[1.02]"
                }`}
            >
                {isSelected && (
                    <div className="absolute z-20 flex items-center justify-center w-5 h-5 text-xs text-white duration-200 bg-gray-900 rounded-full top-2 right-2 animate-in fade-in zoom-in">
                        ✓
                    </div>
                )}
                {isLoading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/50">
                        <Loader2 className="w-5 h-5 text-gray-900 animate-spin" />
                    </div>
                )}
                <div className="w-full aspect-[4/3] flex items-center justify-center bg-transparent rounded-md overflow-hidden">
                    {(cachedImage || image) && !failed ? (
                        <img
                            src={cachedImage || image}
                            alt={label}
                            className="object-contain w-full h-full"
                            onError={() => setFailed(true)}
                        />
                    ) : (
                        <div className="flex items-center justify-center w-full h-full text-xs text-gray-400">
                            {label || "No image"}
                        </div>
                    )}
                </div>
                <div className="mt-2">
                    <div className="text-xs font-medium leading-tight text-center text-gray-700">{label}</div>
                    {price && <div className="text-xs text-center text-gray-500 mt-0.5">${price}</div>}
                </div>
            </button>
        );
    };

    const StyleOptionTile = ({ isSelected, onClick, image, hadDiagramField, label, aspect = "aspect-[3/4]", isLoading }) => {
        const [cachedImage, setCachedImage] = useState(null);
        const [failed, setFailed] = useState(false);
        const showImage = Boolean(cachedImage || image) && !failed;

        useEffect(() => {
            if (image) {
                smartImageCache.preloadImage(image).then(img => {
                    if (img) setCachedImage(img.src);
                });
            }
        }, [image]);

        return (
            <button
                onClick={onClick}
                className={`relative p-3 rounded-lg transition-all duration-300 ease-out ${
                    isSelected
                        ? " rounded-lg bg-transparent scale-[1.03]"
                        : "rounded-lg hover:scale-[1.03]"
                }`}
                title={DEBUG_DIAGRAMS ? image || "no image URL resolved" : undefined}
            >
                {isSelected && (
                    <div className="absolute z-20 flex items-center justify-center w-5 h-5 text-xs text-white duration-200 bg-gray-900 rounded-full top-2 right-2 animate-in fade-in zoom-in">
                        ✓
                    </div>
                )}
                {isLoading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/50">
                        <Loader2 className="w-5 h-5 text-gray-900 animate-spin" />
                    </div>
                )}

                {DEBUG_DIAGRAMS && !hadDiagramField && (
                    <div
                        className="absolute z-20 w-2.5 h-2.5 rounded-full bg-yellow-400 top-2 left-2"
                        title="No `diagram` field on this record"
                    />
                )}
                {DEBUG_DIAGRAMS && hadDiagramField && failed && (
                    <div
                        className="absolute z-20 flex items-center justify-center w-4 h-4 bg-red-500 rounded-full top-2 left-2"
                        title="diagram field present but image failed"
                    >
                        <AlertTriangle className="w-2.5 h-2.5 text-white" />
                    </div>
                )}

                <div className={`w-full ${aspect} flex items-center justify-center rounded-md overflow-hidden bg-white`}>
                    {showImage ? (
                        <img
                            src={cachedImage || image}
                            alt={label}
                            className="object-contain w-full h-full p-2"
                            style={{ mixBlendMode: "multiply" }}
                            onError={() => {
                                if (DEBUG_DIAGRAMS) console.warn(`Failed to load image for "${label}":`, image);
                                setFailed(true);
                            }}
                        />
                    ) : (
                        <div className="flex items-center justify-center w-full h-full p-2 text-xs text-center text-gray-400">
                            <Loader2 className="w-4 h-4 animate-spin" />
                        </div>
                    )}
                </div>
                <div className="mt-2">
                    <div className="text-xs font-medium leading-tight text-center text-gray-700">{label}</div>
                </div>
            </button>
        );
    };

    const LiningOptionTile = ({ isSelected, onClick, image, label, isLoading }) => {
        const [cachedImage, setCachedImage] = useState(null);
        const [failed, setFailed] = useState(false);

        useEffect(() => {
            if (image) {
                smartImageCache.preloadImage(image).then(img => {
                    if (img) setCachedImage(img.src);
                });
            }
        }, [image]);

        return (
            <button
                onClick={onClick}
                className={`relative p-3 rounded-lg transition-all duration-300 ease-out ${
                    isSelected
                        ? " scale-[1.02]"
                        : " hover:scale-[1.02]"
                }`}
            >
                {isSelected && (
                    <div className="absolute z-20 flex items-center justify-center w-5 h-5 text-xs text-white duration-200 bg-gray-900 rounded-full top-2 right-2">
                        ✓
                    </div>
                )}
                {isLoading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/50">
                        <Loader2 className="w-5 h-5 text-gray-900 animate-spin" />
                    </div>
                )}
                <div className="w-full aspect-[4/3] flex items-center justify-center bg-transparent rounded-md overflow-hidden">
                    {(cachedImage || image) && !failed ? (
                        <img
                            src={cachedImage || image}
                            alt={label}
                            className="object-contain w-full h-full p-2"
                            style={{ mixBlendMode: "multiply" }}
                            onError={() => setFailed(true)}
                        />
                    ) : (
                        <div className="flex items-center justify-center w-full h-full text-xs text-gray-400">
                            <Loader2 className="w-4 h-4 animate-spin" />
                        </div>
                    )}
                </div>
            </button>
        );
    };

    const ButtonOptionTile = ({ isSelected, onClick, image, label, isLoading, isDefault = false }) => {
        const [cachedImage, setCachedImage] = useState(null);
        const [failed, setFailed] = useState(false);

        useEffect(() => {
            if (image) {
                smartImageCache.preloadImage(image).then(img => {
                    if (img) setCachedImage(img.src);
                });
            }
        }, [image]);

        return (
            <button
                onClick={onClick}
                className={`relative p-3 rounded-lg transition-all duration-300 ease-out ${
                    isSelected
                        ? " scale-[1.02]"
                        : " hover:scale-[1.02]"
                }`}
            >
                {isSelected && (
                    <div className="absolute z-20 flex items-center justify-center w-5 h-5 text-xs text-white duration-200 bg-gray-900 rounded-full top-2 right-2">
                        ✓
                    </div>
                )}
                {isLoading && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center rounded-lg bg-white/50">
                        <Loader2 className="w-5 h-5 text-gray-900 animate-spin" />
                    </div>
                )}
                <div className="flex items-center justify-center w-full overflow-hidden rounded-md aspect-square transparent">
                    {isDefault ? (
                        <div className="flex flex-col items-center justify-center w-full h-full p-2">
                            <div className="flex items-center justify-center w-12 h-12 mb-1 border-4 border-gray-900 rounded-full">
                                <span className="text-xs text-gray-400">−</span>
                            </div>
                        </div>
                    ) : (cachedImage || image) && !failed ? (
                        <img
                            src={cachedImage || image}
                            alt={label}
                            className="object-contain w-full h-full p-2"
                            style={{ mixBlendMode: "multiply" }}
                            onError={() => setFailed(true)}
                        />
                    ) : (
                        <div className="flex items-center justify-center w-full h-full text-xs text-gray-400">
                            <Loader2 className="w-4 h-4 animate-spin" />
                        </div>
                    )}
                </div>
                <div className="mt-2">
                    <div className="text-xs font-medium leading-tight text-center text-gray-700">{label}</div>
                </div>
            </button>
        );
    };

    const SectionHeader = ({ title }) => (
        <div className="px-5 pt-6 pb-3">
            <span className="text-xs font-bold tracking-wider text-gray-400 uppercase">{title}</span>
        </div>
    );

    const RailTab = ({ id, icon: Icon, label }) => {
        const isActive = activeTab === id;
        return (
            <button
                type="button"
                onClick={() => handleTabChange(id)}
                className="flex flex-col items-center gap-1.5 py-3 group w-full transition-transform duration-200 hover:scale-105"
            >
                <div
                    className={`flex items-center justify-center w-11 h-11 rounded-full border-2 transition-all duration-300 ${
                        isActive
                            ? "bg-gray-900 border-gray-900 text-white shadow-md scale-110"
                            : "bg-white border-gray-200 text-gray-400 group-hover:border-gray-400 group-hover:text-gray-600"
                    }`}
                >
                    <Icon className="w-5 h-5" />
                </div>
                <span
                    className={`text-[11px] font-semibold tracking-wide uppercase transition-colors duration-300 ${
                        isActive ? "text-gray-900" : "text-gray-400"
                    }`}
                >
                    {label}
                </span>
            </button>
        );
    };

    // ---------- LOADING / ERROR STATES ----------
    if (loading) {
        return (
            <div className="flex items-center justify-center h-screen bg-gray-50">
                <div className="text-center duration-500 animate-in fade-in zoom-in">
                    <Loader2 className="w-12 h-12 mx-auto mb-4 text-gray-900 animate-spin" />
                    <p className="text-lg text-gray-600">Loading customization options...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex items-center justify-center h-screen bg-gray-50">
                <div className="max-w-md p-8 text-center duration-500 bg-white shadow-lg rounded-xl animate-in fade-in slide-in-from-bottom-4">
                    <div className="mb-4 text-5xl">⚠️</div>
                    <h2 className="mb-2 text-2xl font-bold text-gray-800">Error Loading Data</h2>
                    <p className="mb-4 text-gray-600">{error}</p>
                    <button
                        onClick={fetchSuitData}
                        className="px-6 py-2 text-white transition-all duration-200 bg-gray-900 rounded-lg hover:bg-gray-800 hover:shadow-md active:scale-95"
                    >
                        Retry
                    </button>
                </div>
            </div>
        );
    }

    if (!suitData || !suitData.success || !selectedFabric) {
        return (
            <div className="flex items-center justify-center h-screen bg-gray-50">
                <p className="text-gray-600">No data available</p>
            </div>
        );
    }

    const lapelCategories = selectedBody ? getLapelCategories(selectedBody.lapels) : [];
    const filteredLapels =
        selectedLapelCategory && selectedBody
            ? selectedBody.lapels.filter((l) => l.category?.id === selectedLapelCategory.id)
            : [];

    // ---------- MAIN RENDER ----------
    return (
        <div className="flex h-screen bg-white">
            <style>{`
                .loader {
                    --color-1: #d1d5db;
                    --size: 2px;
                    width: calc(48 * var(--size));
                    height: calc(48 * var(--size));
                    border: calc(5 * var(--size)) dotted var(--color-1);
                    border-radius: 50%;
                    display: inline-block;
                    position: relative;
                    box-sizing: border-box;
                    animation: rotation 2s linear infinite;
                }
                @keyframes rotation {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `}</style>

            {/* SIDEBAR */}
            <div className="z-10 flex shadow-lg shrink-0">
                <div className="overflow-y-auto bg-white border-r border-gray-100 w-96">
                    <div className="px-5 py-5 border-b border-gray-100">
                        <h1 className="text-xl font-semibold text-gray-900 transition-all duration-300">
                            {activeTab === "fabric" && "Choose your fabric"}
                            {activeTab === "style" && "Customize your style"}
                            {activeTab === "accents" && "Accents & lining"}
                        </h1>
                        <p className="mt-1 text-sm text-gray-500">
                            {activeTab === "fabric" && "Select the material for your suit"}
                            {activeTab === "style" && "Personalize the cut and details"}
                            {activeTab === "accents" && "Add the finishing touches"}
                        </p>
                    </div>

                    {activeTab === "fabric" && (
                        <div className="p-5 duration-300 animate-in fade-in slide-in-from-left-2">
                            <div className="grid grid-cols-2 gap-3">
                                {suitData.data.map((fabric) => (
                                    <FabricOptionTile
                                        key={fabric.id}
                                        isSelected={fabric.id === selectedFabric?.id}
                                        onClick={() => handleFabricChange(fabric)}
                                        image={fabric.image}
                                        label={fabric.name}
                                        price={fabric.price}
                                        isLoading={isFabricLoading && pendingFabric?.id === fabric.id}
                                    />
                                ))}
                            </div>
                        </div>
                    )}

                    {activeTab === "style" && (
                        <div className="pb-5 duration-300 animate-in fade-in slide-in-from-left-2">
                            {selectedFabric?.bodies && selectedFabric.bodies.length > 0 && (
                                <>
                                    <SectionHeader title="Body Style" />
                                    <div className="grid grid-cols-3 gap-3 px-5">
                                        {selectedFabric.bodies.map((body) => (
                                            <StyleOptionTile
                                                key={body.id}
                                                isSelected={body.id === selectedBody?.id}
                                                onClick={() => handleBodyChange(body)}
                                                image={body.body_type?.diagram || body.image}
                                                hadDiagramField={Boolean(body.body_type?.diagram)}
                                                label={body.body_type?.name || "Body"}
                                                isLoading={isBodyLoading && pendingBody?.id === body.id}
                                            />
                                        ))}
                                    </div>
                                </>
                            )}

                            {selectedBody?.lapels && selectedBody.lapels.length > 0 && (
                                <>
                                    <SectionHeader title="Lapel Type" />
                                    <div className="grid grid-cols-3 gap-3 px-5">
                                        {lapelCategories.map((category) => (
                                            <StyleOptionTile
                                                key={category.id}
                                                isSelected={selectedLapelCategory?.id === category.id}
                                                onClick={() => handleLapelCategoryChange(category)}
                                                image={category.diagram}
                                                hadDiagramField={Boolean(category.diagram)}
                                                label={category.name}
                                                isLoading={isLapelCategoryLoading && pendingLapelCategory?.id === category.id}
                                            />
                                        ))}
                                    </div>

                                    {selectedLapelCategory && filteredLapels.length > 0 && (
                                        <>
                                            <SectionHeader title={`${selectedLapelCategory.name} Width`} />
                                            <div className="grid grid-cols-3 gap-3 px-5">
                                                {filteredLapels.map((lapel) => (
                                                    <StyleOptionTile
                                                        key={lapel.id}
                                                        isSelected={lapel.id === selectedLapel?.id}
                                                        onClick={() => handleLapelSelect(lapel)}
                                                        image={lapel.subcategory?.diagram || lapel.image}
                                                        hadDiagramField={Boolean(lapel.subcategory?.diagram)}
                                                        label={lapel.subcategory?.name || "Width"}
                                                        isLoading={isLapelLoading && pendingLapel?.id === lapel.id}
                                                    />
                                                ))}
                                            </div>
                                        </>
                                    )}
                                </>
                            )}

                            {selectedFabric?.sleeves && selectedFabric.sleeves.length > 0 && (
                                <>
                                    <SectionHeader title="Sleeves" />
                                    <div className="grid grid-cols-3 gap-3 px-5">
                                        {selectedFabric.sleeves.map((sleeve) => (
                                            <StyleOptionTile
                                                key={sleeve.id}
                                                isSelected={sleeve.id === selectedSleeve?.id}
                                                onClick={() => handleSleeveSelect(sleeve)}
                                                image={sleeve.type?.diagram || sleeve.image}
                                                hadDiagramField={Boolean(sleeve.type?.diagram)}
                                                label={sleeve.type?.name || "Sleeve"}
                                                isLoading={isSleeveLoading && pendingSleeve?.id === sleeve.id}
                                            />
                                        ))}
                                    </div>
                                </>
                            )}

                            {selectedFabric?.side_pockets && selectedFabric.side_pockets.length > 0 && (
                                <>
                                    <SectionHeader title="Side Pockets" />
                                    <div className="grid grid-cols-3 gap-3 px-5">
                                        {selectedFabric.side_pockets.map((pocket) => (
                                            <StyleOptionTile
                                                key={pocket.id}
                                                isSelected={pocket.id === selectedSidePocket?.id}
                                                onClick={() => handleSidePocketSelect(pocket)}
                                                image={pocket.type?.diagram || pocket.image}
                                                hadDiagramField={Boolean(pocket.type?.diagram)}
                                                label={pocket.type?.name || "Pocket"}
                                                isLoading={isSidePocketLoading && pendingSidePocket?.id === pocket.id}
                                            />
                                        ))}
                                    </div>
                                </>
                            )}

                            {selectedFabric?.chest_pockets && selectedFabric.chest_pockets.length > 0 && (
                                <>
                                    <SectionHeader title="Chest Pockets" />
                                    <div className="grid grid-cols-3 gap-3 px-5">
                                        {selectedFabric.chest_pockets.map((pocket) => (
                                            <StyleOptionTile
                                                key={pocket.id}
                                                isSelected={pocket.id === selectedChestPocket?.id}
                                                onClick={() => handleChestPocketSelect(pocket)}
                                                image={pocket.type?.diagram || pocket.image}
                                                hadDiagramField={Boolean(pocket.type?.diagram)}
                                                label={pocket.type?.name || "Pocket"}
                                                isLoading={isChestPocketLoading && pendingChestPocket?.id === pocket.id}
                                            />
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>
                    )}

                    {activeTab === "accents" && (
                        <div className="p-5 duration-300 animate-in fade-in slide-in-from-left-2">
                            <div className="mb-6">
                                <h3 className="mb-4 text-sm font-semibold tracking-wide text-gray-900 uppercase">
                                    Lining Type
                                </h3>

                                <div className="grid grid-cols-2 gap-3">
                                    {selectedBody?.default_linings?.map((defaultLining) => (
                                        <LiningOptionTile
                                            key={`default-${defaultLining.id}`}
                                            isSelected={liningMode === "default" && !selectedLining}
                                            onClick={() => handleDefaultLiningClick()}
                                            image={defaultLining.type?.diagram || defaultLining.image}
                                            label={defaultLining.type?.name || "Default"}
                                            isLoading={false}
                                        />
                                    ))}

                                    {selectedFabric?.custom_linings && selectedFabric.custom_linings.length > 0 && (
                                        <LiningOptionTile
                                            key="custom-type"
                                            isSelected={liningMode === "custom"}
                                            onClick={handleCustomLiningClick}
                                            image={selectedFabric.custom_linings[0].type?.diagram}
                                            label={selectedFabric.custom_linings[0].type?.name || "Custom"}
                                            isLoading={false}
                                        />
                                    )}
                                </div>
                            </div>

                            {selectedBody?.body_type?.body_buttons && selectedBody.body_type.body_buttons.length > 0 && (
                                <div className="mb-6">
                                    <h3 className="mb-4 text-sm font-semibold tracking-wide text-gray-900 uppercase">
                                        Buttons
                                    </h3>

                                    <div className="grid grid-cols-3 gap-3">
                                        <ButtonOptionTile
                                            key="default-button"
                                            isSelected={!selectedButton}
                                            onClick={() => handleDefaultButtonClick()}
                                            image={null}
                                            label="Default"
                                            isLoading={false}
                                            isDefault={true}
                                        />

                                        {selectedBody.body_type.body_buttons.map((button) => (
                                            <ButtonOptionTile
                                                key={button.id}
                                                isSelected={selectedButton?.id === button.id}
                                                onClick={() => handleButtonSelect(button)}
                                                image={button.button_image?.diagram || button.image}
                                                label={button.button_image?.name || `Button ${button.id}`}
                                                isLoading={isButtonLoading && pendingButton?.id === button.id}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <div className="flex flex-col items-center w-20 gap-4 py-6 border-l border-gray-100 bg-gray-50">
                    <button
                        type="button"
                        className="flex items-center justify-center w-10 h-10 mb-2 text-gray-500 transition-colors rounded-lg hover:bg-gray-100"
                        aria-label="Menu"
                    >
                        <Menu className="w-5 h-5" />
                    </button>
                    <RailTab id="fabric" icon={Layers} label="Fabric" />
                    <RailTab id="style" icon={Scissors} label="Style" />
                    <RailTab id="accents" icon={Palette} label="Accents" />
                </div>
            </div>

            {/* CANVAS */}
            <div className="relative flex items-center justify-center flex-1 overflow-hidden bg-transparent">
                <div className="flex items-center justify-center w-full h-full">
                    <div
                        className="relative w-[600px] h-[800px] bg-transparent rounded-xl overflow-hidden"
                        style={{ width: "600px", height: "800px" }}
                    >
                        {/* If any component is loading, show a blank canvas with spinner */}
                        {isAnyLoading ? (
                            <div className="absolute inset-0 z-50 flex items-center justify-center bg-white">
                                <div className="flex flex-col items-center gap-4">
                                    <span className="loader"></span>
                                    <span className="text-sm font-medium text-gray-500 animate-pulse">Loading...</span>
                                </div>
                            </div>
                        ) : (
                            // Otherwise render the layers (they should be fully cached)
                            layers.map((layer) => (
                                <CanvasLayer key={layer.id} layer={layer} />
                            ))
                        )}
                    </div>
                </div>

                <LoadingIndicator loadingCount={activeLoads} />
            </div>

            {/* MODAL */}
            {showLiningSidebar && (
                <div
                    className="fixed inset-0 z-[1000] flex items-center justify-center bg-black/40 backdrop-blur-sm animate-in fade-in duration-200"
                    onClick={(e) => {
                        if (e.target === e.currentTarget) {
                            setShowLiningSidebar(false);
                        }
                    }}
                >
                    <div className="w-full max-w-md p-6 mx-4 duration-300 bg-white shadow-2xl rounded-xl animate-in zoom-in-95 slide-in-from-bottom-4">
                        <div className="flex items-center justify-between mb-5">
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Select Custom Lining
                                </h2>
                                <p className="mt-1 text-xs text-gray-500">
                                    Choose your preferred lining
                                </p>
                            </div>
                            <button
                                onClick={() => setShowLiningSidebar(false)}
                                className="p-1.5 text-gray-400 transition-all duration-200 rounded-lg hover:text-gray-700 hover:bg-gray-100"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        <div className="grid grid-cols-3 gap-3">
                            {selectedFabric?.custom_linings?.map((lining) => {
                                const isSelected = selectedLining?.id === lining.id;

                                return (
                                    <button
                                        key={lining.id}
                                        onClick={() => handleLiningSelect(lining)}
                                        className={`relative p-3 rounded-lg transition-all duration-300 ease-out ${
                                            isSelected
                                                ? "shadow-md scale-[1.02]"
                                                : "hover:shadow-md hover:scale-[1.02]"
                                        }`}
                                    >
                                        {isSelected && (
                                            <div className="absolute z-20 flex items-center justify-center w-5 h-5 text-xs text-white bg-gray-900 rounded-full top-2 right-2 animate-in fade-in zoom-in">
                                                ✓
                                            </div>
                                        )}

                                        <div className="flex items-center justify-center w-full overflow-hidden bg-transparent rounded-md aspect-[4/3]">
                                            <img
                                                src={lining.fabric?.image || lining.image}
                                                alt={lining.fabric?.name || "Lining"}
                                                className="object-contain w-full h-full"
                                            />
                                        </div>

                                        <div className="mt-2">
                                            <div className="text-xs font-medium leading-tight text-center text-gray-700">
                                                {lining.fabric?.name || "Lining"}
                                            </div>
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

export default SuitDesigner;

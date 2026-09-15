import StoreLayout from "@/Layouts/StoreLayout";
import { getBodyProfile, setBodyProfile, useCart } from "@/lib/store";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { ArrowLeft, ArrowRight, Check, Info, Pencil, Ruler } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

const STEPS = ["Bag", "Body profile", "Delivery & payment", "Done"];

/* A dotted silhouette drawn from a bezier outline, so the page has a figure without any asset. */
const Silhouette = ({ className = "" }) => (
    <svg viewBox="0 0 200 420" className={className} aria-hidden="true">
        <defs>
            <pattern id="dots" width="6" height="6" patternUnits="userSpaceOnUse">
                <circle cx="3" cy="3" r="1.1" fill="currentColor" />
            </pattern>
        </defs>
        <path
            d="M100 8c-16 0-26 12-26 30 0 12 5 22 12 27v10c-22 4-44 12-52 24-10 15-22 56-24 90-1 12 8 16 16 12 6-3 10-14 12-26l10-40v60c-4 46-6 92-2 140 1 10 6 22 8 30 2 7 10 8 14 6 4-3 4-10 3-16-3-26-1-56 2-84 4-30 10-58 12-84 2 26 8 54 12 84 3 28 5 58 2 84-1 6-1 13 3 16 4 2 12 1 14-6 2-8 7-20 8-30 4-48 2-94-2-140v-60l10 40c2 12 6 23 12 26 8 4 17 0 16-12-2-34-14-75-24-90-8-12-30-20-52-24v-10c7-5 12-15 12-27 0-18-10-30-26-30z"
            fill="url(#dots)"
            stroke="currentColor"
            strokeWidth="0.6"
            strokeOpacity="0.25"
        />
    </svg>
);

const Slider = ({ label, unit, value, min, max, onChange }) => (
    <label className="block">
        <span className="flex items-baseline justify-between">
            <span className="text-[15px] font-medium">{label}</span>
            <span className="text-sm text-gray-500 tabular-nums">
                <span className={value == null ? "" : "text-gray-900 font-medium"}>{value ?? "––"}</span> {unit}
            </span>
        </span>
        <input
            type="range"
            min={min}
            max={max}
            value={value ?? Math.round((min + max) / 2)}
            onChange={(e) => onChange(Number(e.target.value))}
            className="slider mt-3 w-full"
            aria-label={label}
        />
    </label>
);

const Card = ({ className = "", children }) => (
    <div className={`rounded-3xl bg-white shadow-[0_1px_0_rgba(0,0,0,0.04),0_20px_50px_-30px_rgba(0,0,0,0.15)] ${className}`}>{children}</div>
);

export default function BodyProfile({ fields, limits, profiles }) {
    const user = usePage().props.auth?.user ?? null;
    const items = useCart();
    const existing = useMemo(() => getBodyProfile(), []);

    // "inputs" -> "review" -> "name"
    const [phase, setPhase] = useState("inputs");
    const [inputs, setInputs] = useState({ height: existing?.height_cm ?? null, weight: existing?.weight_kg ?? null, age: existing?.age ?? null });
    const [measurements, setMeasurements] = useState(existing?.measurements ?? null);
    const [name, setName] = useState(existing?.name ?? "My profile");
    const [showAll, setShowAll] = useState(false);
    const [estimating, setEstimating] = useState(false);
    const [error, setError] = useState(null);
    const [saving, setSaving] = useState(false);
    const abortRef = useRef(null);

    const ready = inputs.height != null && inputs.weight != null && inputs.age != null;
    const fieldKeys = Object.keys(fields);
    const visibleKeys = showAll ? fieldKeys : fieldKeys.slice(0, 8);

    /* Live estimate while the sliders move, debounced and cancellable. */
    useEffect(() => {
        if (!ready || phase !== "inputs") return undefined;
        const t = setTimeout(async () => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;
            setEstimating(true);
            try {
                const res = await fetch(route("checkout.estimate"), {
                    method: "POST",
                    headers: { "Content-Type": "application/json", Accept: "application/json", "X-XSRF-TOKEN": decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || "") },
                    body: JSON.stringify(inputs),
                    signal: controller.signal,
                });
                if (res.ok) setMeasurements((await res.json()).measurements);
            } catch {
                /* superseded or offline */
            } finally {
                if (!controller.signal.aborted) setEstimating(false);
            }
        }, 250);
        return () => clearTimeout(t);
    }, [inputs, ready, phase]);

    const setMeasurement = (key, value) => setMeasurements((m) => ({ ...m, [key]: value }));

    const snapshot = () => ({
        id: null,
        name: name.trim() || "My profile",
        height_cm: inputs.height,
        weight_kg: inputs.weight,
        age: inputs.age,
        measurements: Object.fromEntries(fieldKeys.map((k) => [k, Number(measurements[k])])),
    });

    const finish = async () => {
        const profile = snapshot();
        setError(null);
        if (user) {
            setSaving(true);
            router.post(route("checkout.body-profile.store"), profile, {
                preserveScroll: true,
                onSuccess: (page) => {
                    setBodyProfile(page.props.flash?.profile ?? profile);
                    router.visit(items.length ? route("checkout") : route("dashboard"));
                },
                onError: () => setError("Please check the measurements — every value must be between 10 and 250 cm."),
                onFinish: () => setSaving(false),
            });
            return;
        }
        setBodyProfile(profile);
        router.visit(items.length ? route("checkout") : route("cart"));
    };

    const useSaved = (profile) => {
        setBodyProfile(profile);
        router.visit(items.length ? route("checkout") : route("dashboard"));
    };

    return (
        <StoreLayout steps={STEPS} step={1} secure back={{ href: route("cart"), label: "Back to bag" }} wide>
            <Head title="Body profile" />

            <div className="grid gap-8 lg:grid-cols-12 lg:gap-12">
                {/* Figure */}
                <div className="hidden lg:col-span-5 lg:block">
                    <div className="sticky top-10 rounded-3xl bg-[#efece6] p-10">
                        <Silhouette className="mx-auto h-[520px] w-auto text-gray-900" />
                        <p className="mt-6 text-center text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Your digital body profile</p>
                    </div>
                </div>

                <div className="lg:col-span-7">
                    {phase === "inputs" && (
                        <div className="animate-slide-up">
                            <h1 className="text-4xl font-light tracking-tight sm:text-5xl">
                                Let&rsquo;s create your <br className="hidden sm:block" /> digital body profile
                            </h1>
                            <p className="mt-4 flex items-start gap-2 text-[15px] text-gray-500">
                                <Info className="mt-0.5 h-4 w-4 shrink-0" />
                                Three numbers are enough to estimate your measurements. You will review every one before we cut.
                            </p>

                            <Card className="mt-8 space-y-8 p-7 sm:p-9">
                                <Slider label="Height" unit="cm" value={inputs.height} min={limits.height[0]} max={limits.height[1]} onChange={(v) => setInputs((s) => ({ ...s, height: v }))} />
                                <Slider label="Weight" unit="kg" value={inputs.weight} min={limits.weight[0]} max={limits.weight[1]} onChange={(v) => setInputs((s) => ({ ...s, weight: v }))} />
                                <Slider label="Age" unit="years" value={inputs.age} min={limits.age[0]} max={limits.age[1]} onChange={(v) => setInputs((s) => ({ ...s, age: v }))} />
                            </Card>

                            <div className="mt-8 flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    disabled={!ready || !measurements || estimating}
                                    onClick={() => setPhase("review")}
                                    className="btn-ink bg-gray-900 text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400"
                                >
                                    {estimating ? "Estimating…" : "Estimate my body profile"} <ArrowRight className="h-4 w-4" />
                                </button>
                                {profiles.length > 0 && (
                                    <details className="group relative">
                                        <summary className="btn-ink btn-ink--outline cursor-pointer list-none">I already have a profile</summary>
                                        <div className="absolute left-0 z-10 mt-2 w-72 overflow-hidden rounded-2xl border border-gray-100 bg-white p-1.5 shadow-[0_20px_50px_-20px_rgba(0,0,0,0.25)]">
                                            {profiles.map((p) => (
                                                <button key={p.id} type="button" onClick={() => useSaved(p)} className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors hover:bg-gray-100">
                                                    <Ruler className="h-4 w-4 text-gray-400" />
                                                    <span>
                                                        <span className="block text-sm font-medium">{p.name}</span>
                                                        <span className="block text-xs text-gray-500">{p.height_cm} cm · {p.weight_kg} kg · {p.age} years</span>
                                                    </span>
                                                </button>
                                            ))}
                                        </div>
                                    </details>
                                )}
                            </div>
                        </div>
                    )}

                    {phase === "review" && measurements && (
                        <div className="animate-slide-up">
                            <div className="flex items-center gap-2">
                                <h1 className="text-3xl font-light tracking-tight sm:text-4xl">Your estimated measurements</h1>
                                <Info className="h-4 w-4 text-gray-400" title="Estimated from your height, weight and age. Tap any value to adjust it." />
                            </div>
                            <p className="mt-3 text-[15px] text-gray-500">Tap a value to adjust it. Our tailors confirm everything before cutting.</p>

                            <div className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                                {visibleKeys.map((key) => (
                                    <label key={key} className="group relative rounded-2xl border border-gray-200 bg-white p-4 transition-colors focus-within:border-gray-900 hover:border-gray-400">
                                        <span className="block text-xs text-gray-500">{fields[key]}</span>
                                        <span className="mt-2 flex items-baseline gap-1">
                                            <input
                                                type="number"
                                                step="0.5"
                                                min="10"
                                                max="250"
                                                value={measurements[key] ?? ""}
                                                onChange={(e) => setMeasurement(key, e.target.value)}
                                                className="w-full border-0 bg-transparent p-0 text-xl font-medium tracking-tight focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                            />
                                            <span className="text-sm text-gray-500">cm</span>
                                        </span>
                                        <Pencil className="absolute right-3 top-3 h-3.5 w-3.5 text-gray-300 group-hover:text-gray-500" />
                                    </label>
                                ))}
                                {!showAll && fieldKeys.length > 8 && (
                                    <button type="button" onClick={() => setShowAll(true)} className="flex flex-col justify-between rounded-2xl border border-dashed border-gray-300 p-4 text-left text-sm text-gray-500 transition-colors hover:border-gray-900 hover:text-gray-900">
                                        Show more
                                        <ArrowRight className="h-5 w-5" />
                                    </button>
                                )}
                            </div>

                            <div className="mt-10 flex flex-wrap items-center justify-between gap-3">
                                <button type="button" onClick={() => setPhase("inputs")} className="btn-ink btn-ink--outline">
                                    <ArrowLeft className="h-4 w-4" /> Change height / weight
                                </button>
                                <button type="button" onClick={() => setPhase("name")} className="btn-ink bg-gray-900 text-white hover:bg-gray-700">
                                    Create profile <ArrowRight className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    )}

                    {phase === "name" && measurements && (
                        <div className="animate-slide-up">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-gray-400">Digital profile created</p>
                            <label className="mt-6 block">
                                <span className="text-sm text-gray-500">Give it a name</span>
                                <input
                                    type="text"
                                    value={name}
                                    onChange={(e) => setName(e.target.value)}
                                    maxLength={80}
                                    className="mt-1 block w-full border-0 border-b border-gray-300 bg-transparent px-0 py-2 text-3xl font-light tracking-tight focus:border-gray-900 focus:ring-0"
                                />
                            </label>

                            <Card className="mt-8 p-7">
                                <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm">
                                    <span className="font-medium">Inputs:</span>
                                    <span>{inputs.age} years</span>
                                    <span>{inputs.height} cm</span>
                                    <span>{inputs.weight} kg</span>
                                    <button type="button" onClick={() => setPhase("inputs")} className="inline-flex items-center gap-1 rounded-full border border-gray-300 px-3 py-1 text-xs transition-colors hover:border-gray-900">
                                        <Pencil className="h-3 w-3" /> Edit
                                    </button>
                                </div>
                                <p className="mt-6 font-medium">Body measurements:</p>
                                <dl className="mt-3 grid gap-x-8 gap-y-1.5 text-sm sm:grid-cols-2">
                                    {fieldKeys.map((key) => (
                                        <div key={key} className="flex justify-between gap-4 sm:justify-start">
                                            <dt className="text-gray-500">{fields[key]}:</dt>
                                            <dd className="font-medium">{measurements[key]} cm</dd>
                                        </div>
                                    ))}
                                </dl>
                                <button type="button" onClick={() => setPhase("review")} className="mt-4 inline-flex items-center gap-1 rounded-full border border-gray-300 px-3 py-1 text-xs transition-colors hover:border-gray-900">
                                    <Pencil className="h-3 w-3" /> Edit
                                </button>
                            </Card>

                            {error && <p className="mt-4 text-sm text-red-700">{error}</p>}

                            <div className="mt-8 flex flex-wrap items-center justify-between gap-3">
                                <button type="button" onClick={() => { setPhase("inputs"); setMeasurements(null); setInputs({ height: null, weight: null, age: null }); }} className="btn-ink btn-ink--outline">
                                    Create new body profile
                                </button>
                                <button type="button" onClick={finish} disabled={saving} className="btn-ink bg-gray-900 text-white hover:bg-gray-700 disabled:opacity-60">
                                    {saving ? "Saving…" : items.length ? "Save it and continue" : "Save profile"} {!saving && <Check className="h-4 w-4" />}
                                </button>
                            </div>
                            {!user && (
                                <p className="mt-4 text-xs text-gray-500">
                                    Saved in this browser. <Link href={route("register")} className="text-gray-900 underline-offset-4 hover:underline">Create an account</Link> to keep it with your orders.
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </StoreLayout>
    );
}

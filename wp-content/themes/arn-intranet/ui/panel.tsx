import * as React from "react"
import { createRoot } from "react-dom/client"
import { ArrowUpRight, Bell, Check, ChevronRight, LayoutDashboard, LogOut, Search, ShieldCheck, SlidersHorizontal } from "lucide-react"
import { Button } from "./components/ui/button"
import { Badge } from "./components/ui/badge"
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "./components/ui/accordion"
import { Carousel, CarouselContent, CarouselItem, CarouselNext, CarouselPrevious, type CarouselApi } from "./components/ui/carousel"

type Notice = { id: number; title: string; body: string; version: number }
type Data = { notices: Notice[]; home_url: string; next_url: string | null; logout_url: string; logo_url: string }

function Panel({ data }: { data: Data }) {
  const [api, setApi] = React.useState<CarouselApi>()
  const [slide, setSlide] = React.useState(0)
  React.useEffect(() => {
    if (!api) return
    const select = () => setSlide(api.selectedScrollSnap())
    select(); api.on('select', select)
    return () => { api.off('select', select) }
  }, [api])
  const [query, setQuery] = React.useState("")
  const [opened, setOpened] = React.useState<string | undefined>()
  const notices = data.notices.filter(n => `${n.title} ${n.body}`.toLocaleLowerCase("pt-PT").includes(query.toLocaleLowerCase("pt-PT")))
  function readNotice(id: number) {
    setQuery(""); setOpened(String(id))
    requestAnimationFrame(() => document.getElementById(`notice-${id}`)?.focus())
  }
  return <>
    <a className="panel-skip" href="#main">Saltar para o conteúdo</a>
    <aside className="panel-sidebar" aria-label="Navegação principal">
      <a className="panel-brand" href={data.home_url}><img src={data.logo_url} alt="ARN" /><span>Plataforma interna<small>Espaço institucional</small></span></a>
      <span className="panel-label">ÁREA DE TRABALHO</span>
      <a className="panel-nav-active" href={data.home_url} aria-current="page"><LayoutDashboard size={19} />O meu painel<ChevronRight size={16} /></a>
      <a className="panel-nav" href="#comunicados"><Bell size={19} />Comunicados</a>
      <div className="panel-sidebar-bottom"><ShieldCheck size={20} /><p>Acesso institucional<span>Informação reservada aos colaboradores autorizados.</span></p></div>
    </aside>
    <div className="panel-workspace">
      <header className="panel-topbar"><span>Plataforma ARN <ChevronRight size={14} /> Painel</span><Button variant="ghost" asChild><a href={data.logout_url}><LogOut />Terminar sessão</a></Button></header>
      <main id="main" tabIndex={-1}>
        <section className="panel-intro"><div><p className="panel-eyebrow">INFORMAÇÃO QUE NOS LIGA</p><h1>O teu espaço institucional.</h1><p>Acompanha a comunicação da ARN, num único lugar.</p></div><Badge className="panel-session"><Check size={14} />Sessão institucional</Badge></section>
        <div className="panel-columns">
          <div className="panel-primary">
            {data.notices.length > 0 && <section className="panel-feature" aria-label="Em destaque">
              <div className="panel-feature-heading"><span>EM DESTAQUE</span><span>Comunicação institucional</span></div>
              <Carousel setApi={setApi} opts={{ loop: false, duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 20 }} aria-label="Comunicados em destaque">
                <CarouselContent>{data.notices.slice(0, 3).map((n, index) => <CarouselItem key={n.id} aria-hidden={index !== slide} inert={index !== slide}><div className="panel-slide"><h2>{n.title}</h2><p>{n.body.length > 180 ? n.body.slice(0, 180) + '…' : n.body}</p><Button className="panel-feature-button" onClick={() => readNotice(n.id)}>Ler comunicado<ArrowUpRight /></Button></div></CarouselItem>)}</CarouselContent>
                {data.notices.length > 1 && <div className="panel-carousel-controls"><CarouselPrevious /><CarouselNext /></div>}
              </Carousel>
            </section>}
            <section id="comunicados" className="panel-notices" aria-labelledby="notices-title">
              <div className="panel-section-heading"><div><p className="panel-eyebrow">PARA TODOS OS COLABORADORES</p><h2 id="notices-title">Comunicados</h2></div><SlidersHorizontal size={20} aria-hidden="true" /></div>
              <label className="panel-search"><Search size={19} /><span className="sr-only">Pesquisar nesta página de comunicados</span><input type="search" placeholder="Pesquisar nesta página…" value={query} onChange={e => setQuery(e.target.value)} /></label>
              <p className="panel-results" role="status">{notices.length} {notices.length === 1 ? 'comunicado nesta página' : 'comunicados nesta página'}</p>
              {notices.length ? <Accordion type="single" collapsible value={opened} onValueChange={setOpened}>{notices.map(n => <AccordionItem key={n.id} value={String(n.id)}><AccordionTrigger id={`notice-${n.id}`}><span><Badge variant="secondary">Institucional</Badge><span className="panel-notice-title">{n.title}</span><small>Versão {n.version} · Para todos os colaboradores</small></span></AccordionTrigger><AccordionContent><p className="panel-notice-body">{n.body}</p></AccordionContent></AccordionItem>)}</Accordion> : <div className="panel-empty"><Bell size={28} /><h3>{query ? 'Sem resultados nesta página' : 'A comunicação começa aqui'}</h3><p>{query ? 'Experimenta outro termo ou continua a consulta.' : 'Os comunicados publicados para a instituição aparecerão neste espaço.'}</p>{query && <Button variant="outline" onClick={() => setQuery('')}>Limpar pesquisa</Button>}</div>}
              {data.next_url && <Button variant="outline" asChild className="panel-next"><a href={data.next_url}>Continuar a consulta<ChevronRight /></a></Button>}
            </section>
          </div>
          <aside className="panel-context" aria-label="Sobre este espaço"><div className="panel-context-icon"><ShieldCheck size={25} /></div><h2>Um espaço da instituição.</h2><p>Consulta a informação publicada para todos os colaboradores com acesso à plataforma.</p><hr /><h3>Leitura organizada</h3><p>Abre um comunicado para ler o conteúdo completo. Usa a pesquisa para encontrar texto nesta página.</p><div className="panel-context-note"><Check size={16} /><span>Apenas versões publicadas e válidas.</span></div></aside>
        </div>
        <footer className="panel-footer">ARN · Plataforma interna<span>Informação institucional</span></footer>
      </main>
    </div>
  </>
}

const root = document.getElementById("arn-panel-root")
const payload = document.getElementById("arn-panel-data")
if (root && payload) {
  try { const data = JSON.parse(payload.textContent || ""); createRoot(root).render(<Panel data={data} />) }
  catch { /* Preserve the server-rendered reading surface. */ }
}
window.addEventListener('pageshow', e => { if (e.persisted) window.location.reload() })

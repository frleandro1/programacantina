import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { BarChart3, ShoppingCart, Package, TrendingUp } from "lucide-react";
import { useState } from "react";
import ProductionScreen from "./ProductionScreen";
import SalesScreen from "./SalesScreen";
import SalesHistory from "./SalesHistory";

export default function Home() {
  const [activeTab, setActiveTab] = useState("overview");

  return (
    <div className="min-h-screen bg-gradient-to-br from-cyan-50 via-white to-green-50">
      {/* Header */}
      <header className="sticky top-0 z-50 bg-white shadow-sm border-b border-cyan-200">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="bg-gradient-to-br from-cyan-600 to-green-600 p-2 rounded-lg">
                <ShoppingCart className="w-6 h-6 text-white" />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900">Programa Cantina</h1>
                <p className="text-sm text-gray-600">Sistema de Gerenciamento Integrado</p>
              </div>
            </div>
            <div className="hidden md:flex items-center gap-2">
              <span className="text-sm text-gray-600">Status:</span>
              <div className="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
              <span className="text-sm font-medium text-green-700">Online</span>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="container mx-auto px-4 py-8">
        <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
          <TabsList className="grid w-full grid-cols-4 mb-8">
            <TabsTrigger value="overview" className="flex items-center gap-2">
              <BarChart3 className="w-4 h-4" />
              <span className="hidden sm:inline">Visão Geral</span>
            </TabsTrigger>
            <TabsTrigger value="production" className="flex items-center gap-2">
              <Package className="w-4 h-4" />
              <span className="hidden sm:inline">Produção</span>
            </TabsTrigger>
            <TabsTrigger value="sales" className="flex items-center gap-2">
              <ShoppingCart className="w-4 h-4" />
              <span className="hidden sm:inline">Vendas</span>
            </TabsTrigger>
            <TabsTrigger value="history" className="flex items-center gap-2">
              <TrendingUp className="w-4 h-4" />
              <span className="hidden sm:inline">Histórico</span>
            </TabsTrigger>
          </TabsList>

          {/* Overview Tab */}
          <TabsContent value="overview" className="space-y-6">
            {/* Hero Section */}
            <div className="relative overflow-hidden rounded-2xl bg-gradient-to-r from-cyan-600 to-green-600 text-white p-8 md:p-12">
              <div className="absolute top-0 right-0 w-96 h-96 bg-white/10 rounded-full -mr-48 -mt-48"></div>
              <div className="relative z-10">
                <h2 className="text-3xl md:text-4xl font-bold mb-4">
                  Bem-vindo ao Programa Cantina
                </h2>
                <p className="text-lg text-cyan-50 mb-6 max-w-2xl">
                  Uma solução simples e eficiente para automatizar o controle de estoque, 
                  produção e vendas em cantinas escolares e empresariais.
                </p>
                <div className="flex flex-wrap gap-3">
                  <Button 
                    size="lg" 
                    className="bg-white text-cyan-600 hover:bg-cyan-50"
                    onClick={() => setActiveTab("production")}
                  >
                    Ir para Produção
                  </Button>
                  <Button 
                    size="lg" 
                    variant="outline" 
                    className="border-white text-white hover:bg-white/20"
                    onClick={() => setActiveTab("sales")}
                  >
                    Ir para Vendas
                  </Button>
                </div>
              </div>
            </div>

            {/* Stats Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <Card className="border-cyan-200 hover:shadow-lg transition-shadow">
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium text-gray-600">Produtos Cadastrados</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-3xl font-bold text-cyan-600">24</div>
                  <p className="text-xs text-gray-500 mt-2">Ativos no sistema</p>
                </CardContent>
              </Card>

              <Card className="border-green-200 hover:shadow-lg transition-shadow">
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium text-gray-600">Estoque Total</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-3xl font-bold text-green-600">1,250</div>
                  <p className="text-xs text-gray-500 mt-2">Unidades em estoque</p>
                </CardContent>
              </Card>

              <Card className="border-blue-200 hover:shadow-lg transition-shadow">
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium text-gray-600">Vendas Hoje</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-3xl font-bold text-blue-600">342</div>
                  <p className="text-xs text-gray-500 mt-2">Itens vendidos</p>
                </CardContent>
              </Card>

              <Card className="border-orange-200 hover:shadow-lg transition-shadow">
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium text-gray-600">Desperdício</CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-3xl font-bold text-orange-600">12</div>
                  <p className="text-xs text-gray-500 mt-2">Itens descartados</p>
                </CardContent>
              </Card>
            </div>

            {/* Features Section */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <Card className="border-cyan-200">
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Package className="w-5 h-5 text-cyan-600" />
                    Tela de Produção
                  </CardTitle>
                  <CardDescription>
                    Registre produtos recém-produzidos
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                  <p className="text-sm text-gray-700">
                    Permite que o responsável pela produção registre e atualize a quantidade 
                    de itens produzidos em tempo real.
                  </p>
                  <ul className="text-sm space-y-2 text-gray-600">
                    <li className="flex items-center gap-2">
                      <div className="w-2 h-2 bg-cyan-600 rounded-full"></div>
                      Seleção rápida de produtos
                    </li>
                    <li className="flex items-center gap-2">
                      <div className="w-2 h-2 bg-cyan-600 rounded-full"></div>
                      Atualização automática de estoque
                    </li>
                    <li className="flex items-center gap-2">
                      <div className="w-2 h-2 bg-cyan-600 rounded-full"></div>
                      Registro com data/hora
                    </li>
                  </ul>
                  <Button 
                    className="w-full mt-4 bg-cyan-600 hover:bg-cyan-700"
                    onClick={() => setActiveTab("production")}
                  >
                    Acessar Produção
                  </Button>
                </CardContent>
              </Card>

              <Card className="border-green-200">
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <ShoppingCart className="w-5 h-5 text-green-600" />
                    Tela de Vendas
                  </CardTitle>
                  <CardDescription>
                    Registre produtos vendidos
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                  <p className="text-sm text-gray-700">
                    Permite que o atendente registre rapidamente a saída de produtos vendidos 
                    com validação automática de estoque.
                  </p>
                  <ul className="text-sm space-y-2 text-gray-600">
                    <li className="flex items-center gap-2">
                      <div className="w-2 h-2 bg-green-600 rounded-full"></div>
                      Validação de estoque
                    </li>
                    <li className="flex items-center gap-2">
                      <div className="w-2 h-2 bg-green-600 rounded-full"></div>
                      Múltiplos produtos por venda
                    </li>
                    <li className="flex items-center gap-2">
                      <div className="w-2 h-2 bg-green-600 rounded-full"></div>
                      Confirmação instantânea
                    </li>
                  </ul>
                  <Button 
                    className="w-full mt-4 bg-green-600 hover:bg-green-700"
                    onClick={() => setActiveTab("sales")}
                  >
                    Acessar Vendas
                  </Button>
                </CardContent>
              </Card>
            </div>

            {/* Info Section */}
            <Card className="bg-gradient-to-r from-cyan-50 to-green-50 border-cyan-200">
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <TrendingUp className="w-5 h-5 text-cyan-600" />
                  Benefícios do Sistema
                </CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <h4 className="font-semibold text-gray-900 mb-2">Redução de Erros</h4>
                    <p className="text-sm text-gray-700">
                      Elimina registros manuais em papel e reduz discrepâncias de estoque.
                    </p>
                  </div>
                  <div>
                    <h4 className="font-semibold text-gray-900 mb-2">Maior Eficiência</h4>
                    <p className="text-sm text-gray-700">
                      Agiliza o atendimento e reduz filas durante os horários de pico.
                    </p>
                  </div>
                  <div>
                    <h4 className="font-semibold text-gray-900 mb-2">Transparência</h4>
                    <p className="text-sm text-gray-700">
                      Oferece visibilidade total do estoque para gestores e responsáveis.
                    </p>
                  </div>
                  <div>
                    <h4 className="font-semibold text-gray-900 mb-2">Sustentabilidade</h4>
                    <p className="text-sm text-gray-700">
                      Monitora desperdício e ajuda a reduzir perdas de alimentos.
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>
          </TabsContent>

          {/* Production Tab */}
          <TabsContent value="production">
            <ProductionScreen />
          </TabsContent>

          {/* Sales Tab */}
          <TabsContent value="sales">
            <SalesScreen />
          </TabsContent>

          {/* Sales History Tab */}
          <TabsContent value="history">
            <SalesHistory />
          </TabsContent>
        </Tabs>
      </main>

      {/* Footer */}
      <footer className="border-t border-gray-200 bg-white mt-12">
        <div className="container mx-auto px-4 py-8">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div>
              <h3 className="font-semibold text-gray-900 mb-3">Programa Cantina</h3>
              <p className="text-sm text-gray-600">
                Solução de automação para cantinas escolares e empresariais.
              </p>
            </div>
            <div>
              <h3 className="font-semibold text-gray-900 mb-3">Funcionalidades</h3>
              <ul className="text-sm text-gray-600 space-y-2">
                <li>Gestão de Estoque</li>
                <li>Controle de Produção</li>
                <li>Registro de Vendas</li>
                <li>Relatórios</li>
              </ul>
            </div>
            <div>
              <h3 className="font-semibold text-gray-900 mb-3">Informações</h3>
              <p className="text-sm text-gray-600">
                Trabalho de Conclusão de Curso - Técnico em Informática
              </p>
              <p className="text-xs text-gray-500 mt-2">
                ETEC Marechal Mascarenhas de Moraes
              </p>
            </div>
          </div>
          <div className="border-t border-gray-200 mt-8 pt-8 text-center text-sm text-gray-600">
            <p>© 2024 Programa Cantina. Todos os direitos reservados.</p>
          </div>
        </div>
      </footer>
    </div>
  );
}

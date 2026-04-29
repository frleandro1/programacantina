import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { BarChart, Bar, LineChart, Line, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from "recharts";
import { Calendar, Download, Filter, TrendingUp, DollarSign, ShoppingCart, Eye } from "lucide-react";
import { useState, useMemo } from "react";

interface SalesHistoryItem {
  id: string;
  date: string;
  attendant: string;
  items: Array<{
    productName: string;
    quantity: number;
    price: number;
  }>;
  total: number;
  itemCount: number;
}

const SALES_DATA: SalesHistoryItem[] = [
  {
    id: "001",
    date: "2024-01-15 10:30",
    attendant: "Ana Costa",
    items: [
      { productName: "Pão de Queijo", quantity: 10, price: 3.5 },
      { productName: "Suco Natural", quantity: 5, price: 4.5 },
    ],
    total: 57.5,
    itemCount: 15,
  },
  {
    id: "002",
    date: "2024-01-15 11:00",
    attendant: "Pedro Oliveira",
    items: [{ productName: "Coxinha", quantity: 8, price: 4.0 }],
    total: 32.0,
    itemCount: 8,
  },
  {
    id: "003",
    date: "2024-01-15 11:45",
    attendant: "Maria Silva",
    items: [
      { productName: "Bolo de Cenoura", quantity: 3, price: 5.0 },
      { productName: "Café", quantity: 12, price: 2.0 },
    ],
    total: 39.0,
    itemCount: 15,
  },
  {
    id: "004",
    date: "2024-01-15 12:15",
    attendant: "Ana Costa",
    items: [
      { productName: "Sanduíche", quantity: 4, price: 8.0 },
      { productName: "Salada", quantity: 2, price: 6.0 },
    ],
    total: 44.0,
    itemCount: 6,
  },
  {
    id: "005",
    date: "2024-01-15 13:00",
    attendant: "João Santos",
    items: [
      { productName: "Brigadeiro", quantity: 20, price: 2.5 },
      { productName: "Pão de Queijo", quantity: 15, price: 3.5 },
    ],
    total: 102.5,
    itemCount: 35,
  },
  {
    id: "006",
    date: "2024-01-15 13:30",
    attendant: "Pedro Oliveira",
    items: [{ productName: "Suco Natural", quantity: 10, price: 4.5 }],
    total: 45.0,
    itemCount: 10,
  },
  {
    id: "007",
    date: "2024-01-15 14:00",
    attendant: "Maria Silva",
    items: [
      { productName: "Coxinha", quantity: 12, price: 4.0 },
      { productName: "Brigadeiro", quantity: 8, price: 2.5 },
    ],
    total: 68.0,
    itemCount: 20,
  },
  {
    id: "008",
    date: "2024-01-15 14:45",
    attendant: "Ana Costa",
    items: [
      { productName: "Bolo de Cenoura", quantity: 2, price: 5.0 },
      { productName: "Café", quantity: 8, price: 2.0 },
    ],
    total: 26.0,
    itemCount: 10,
  },
];

export default function SalesHistory() {
  const [filterAttendant, setFilterAttendant] = useState("");
  const [filterDateFrom, setFilterDateFrom] = useState("");
  const [filterDateTo, setFilterDateTo] = useState("");
  const [expandedSale, setExpandedSale] = useState<string | null>(null);

  const filteredSales = useMemo(() => {
    return SALES_DATA.filter((sale) => {
      if (filterAttendant && !sale.attendant.toLowerCase().includes(filterAttendant.toLowerCase())) {
        return false;
      }
      if (filterDateFrom && sale.date < filterDateFrom) {
        return false;
      }
      if (filterDateTo && sale.date > filterDateTo) {
        return false;
      }
      return true;
    });
  }, [filterAttendant, filterDateFrom, filterDateTo]);

  // Dados para gráficos
  const dailyRevenue = useMemo(() => {
    const grouped: { [key: string]: number } = {};
    filteredSales.forEach((sale) => {
      const date = sale.date.split(" ")[0];
      grouped[date] = (grouped[date] || 0) + sale.total;
    });
    return Object.entries(grouped).map(([date, total]) => ({
      date,
      total: parseFloat(total.toFixed(2)),
    }));
  }, [filteredSales]);

  const attendantPerformance = useMemo(() => {
    const grouped: { [key: string]: { sales: number; total: number } } = {};
    filteredSales.forEach((sale) => {
      if (!grouped[sale.attendant]) {
        grouped[sale.attendant] = { sales: 0, total: 0 };
      }
      grouped[sale.attendant].sales += 1;
      grouped[sale.attendant].total += sale.total;
    });
    return Object.entries(grouped).map(([name, data]) => ({
      name,
      vendas: data.sales,
      total: parseFloat(data.total.toFixed(2)),
    }));
  }, [filteredSales]);

  const productSales = useMemo(() => {
    const grouped: { [key: string]: number } = {};
    filteredSales.forEach((sale) => {
      sale.items.forEach((item) => {
        grouped[item.productName] = (grouped[item.productName] || 0) + item.quantity;
      });
    });
    return Object.entries(grouped)
      .map(([name, quantity]) => ({ name, value: quantity }))
      .sort((a, b) => b.value - a.value)
      .slice(0, 6);
  }, [filteredSales]);

  const COLORS = ["#0891b2", "#10b981", "#f59e0b", "#ef4444", "#8b5cf6", "#ec4899"];

  // Cálculos de estatísticas
  const stats = useMemo(() => {
    const totalSales = filteredSales.length;
    const totalRevenue = filteredSales.reduce((sum, sale) => sum + sale.total, 0);
    const totalItems = filteredSales.reduce((sum, sale) => sum + sale.itemCount, 0);
    const averageTicket = totalSales > 0 ? totalRevenue / totalSales : 0;

    return {
      totalSales,
      totalRevenue,
      totalItems,
      averageTicket,
    };
  }, [filteredSales]);

  const handleClearFilters = () => {
    setFilterAttendant("");
    setFilterDateFrom("");
    setFilterDateTo("");
  };

  return (
    <div className="space-y-6">
      {/* Hero Image */}
      <div className="relative rounded-xl overflow-hidden h-64 md:h-80">
        <img
          src="/inventory-management.jpg"
          alt="Histórico de Vendas"
          className="w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-r from-green-900/70 to-transparent flex items-center">
          <div className="text-white p-6 md:p-8">
            <h2 className="text-3xl md:text-4xl font-bold mb-2">Histórico de Vendas</h2>
            <p className="text-green-100">Análise detalhada de todas as transações realizadas</p>
          </div>
        </div>
      </div>

      {/* Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="border-green-200 hover:shadow-lg transition-shadow">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm font-medium text-gray-600 flex items-center gap-2">
              <ShoppingCart className="w-4 h-4 text-green-600" />
              Total de Vendas
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-green-600">{stats.totalSales}</div>
            <p className="text-xs text-gray-500 mt-2">Transações registradas</p>
          </CardContent>
        </Card>

        <Card className="border-blue-200 hover:shadow-lg transition-shadow">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm font-medium text-gray-600 flex items-center gap-2">
              <DollarSign className="w-4 h-4 text-blue-600" />
              Receita Total
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-blue-600">R$ {stats.totalRevenue.toFixed(2)}</div>
            <p className="text-xs text-gray-500 mt-2">Faturamento total</p>
          </CardContent>
        </Card>

        <Card className="border-cyan-200 hover:shadow-lg transition-shadow">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm font-medium text-gray-600 flex items-center gap-2">
              <TrendingUp className="w-4 h-4 text-cyan-600" />
              Ticket Médio
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-cyan-600">R$ {stats.averageTicket.toFixed(2)}</div>
            <p className="text-xs text-gray-500 mt-2">Por transação</p>
          </CardContent>
        </Card>

        <Card className="border-orange-200 hover:shadow-lg transition-shadow">
          <CardHeader className="pb-3">
            <CardTitle className="text-sm font-medium text-gray-600 flex items-center gap-2">
              <ShoppingCart className="w-4 h-4 text-orange-600" />
              Total de Itens
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-orange-600">{stats.totalItems}</div>
            <p className="text-xs text-gray-500 mt-2">Unidades vendidas</p>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card className="border-gray-200">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Filter className="w-5 h-5 text-gray-600" />
            Filtros
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div className="space-y-2">
              <Label htmlFor="attendant" className="text-gray-700 font-semibold">
                Atendente
              </Label>
              <Input
                id="attendant"
                placeholder="Filtrar por atendente"
                value={filterAttendant}
                onChange={(e) => setFilterAttendant(e.target.value)}
                className="border-gray-200"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="dateFrom" className="text-gray-700 font-semibold">
                Data Inicial
              </Label>
              <Input
                id="dateFrom"
                type="date"
                value={filterDateFrom}
                onChange={(e) => setFilterDateFrom(e.target.value)}
                className="border-gray-200"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="dateTo" className="text-gray-700 font-semibold">
                Data Final
              </Label>
              <Input
                id="dateTo"
                type="date"
                value={filterDateTo}
                onChange={(e) => setFilterDateTo(e.target.value)}
                className="border-gray-200"
              />
            </div>
          </div>

          <div className="flex gap-2 mt-4">
            <Button
              onClick={handleClearFilters}
              variant="outline"
              className="border-gray-200"
            >
              Limpar Filtros
            </Button>
            <Button className="bg-green-600 hover:bg-green-700 ml-auto">
              <Download className="w-4 h-4 mr-2" />
              Exportar Relatório
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Charts */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Daily Revenue Chart */}
        <Card className="border-green-200">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TrendingUp className="w-5 h-5 text-green-600" />
              Receita Diária
            </CardTitle>
            <CardDescription>
              Faturamento por dia
            </CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={dailyRevenue}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                <XAxis dataKey="date" stroke="#6b7280" />
                <YAxis stroke="#6b7280" />
                <Tooltip
                  contentStyle={{ backgroundColor: "#fff", border: "1px solid #e5e7eb" }}
                  formatter={(value: any) => `${value} un`}
                />
                <Legend />
                <Line
                  type="monotone"
                  dataKey="total"
                  stroke="#10b981"
                  strokeWidth={2}
                  name="Receita"
                  dot={{ fill: "#10b981", r: 5 }}
                />
              </LineChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        {/* Attendant Performance */}
        <Card className="border-blue-200">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TrendingUp className="w-5 h-5 text-blue-600" />
              Desempenho por Atendente
            </CardTitle>
            <CardDescription>
              Vendas e faturamento por atendente
            </CardDescription>
          </CardHeader>
          <CardContent>
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={attendantPerformance}>
                <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                <XAxis dataKey="name" stroke="#6b7280" />
                <YAxis stroke="#6b7280" />
                <Tooltip
                  contentStyle={{ backgroundColor: "#fff", border: "1px solid #e5e7eb" }}
                  formatter={(value: any) => {
                    if (typeof value === "number" && value > 100) {
                      return `R$ ${value.toFixed(2)}`;
                    }
                    return value;
                  }}
                />
                <Legend />
                <Bar dataKey="vendas" fill="#0891b2" name="Qtd Vendas" />
                <Bar dataKey="total" fill="#10b981" name="Total (R$)" />
              </BarChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>
      </div>

      {/* Products Chart */}
      <Card className="border-cyan-200">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ShoppingCart className="w-5 h-5 text-cyan-600" />
            Produtos Mais Vendidos
          </CardTitle>
          <CardDescription>
            Top 6 produtos por quantidade
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie
                  data={productSales}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={({ name, value }) => `${name}: ${value}`}
                  outerRadius={80}
                  fill="#8884d8"
                  dataKey="value"
                >
                  {productSales.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                  ))}
                </Pie>
                <Tooltip formatter={(value) => `${value} un`} />
              </PieChart>
            </ResponsiveContainer>

            <div className="space-y-3">
              {productSales.map((product, index) => (
                <div key={product.name} className="flex items-center gap-3">
                  <div
                    className="w-4 h-4 rounded"
                    style={{ backgroundColor: COLORS[index % COLORS.length] }}
                  ></div>
                  <div className="flex-1">
                    <p className="font-medium text-gray-900">{product.name}</p>
                    <p className="text-sm text-gray-600">{product.value} unidades</p>
                  </div>
                  <p className="font-semibold text-gray-900">{product.value}</p>
                </div>
              ))}
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Sales Table */}
      <Card className="border-green-200">
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Calendar className="w-5 h-5 text-green-600" />
            Detalhamento de Vendas
          </CardTitle>
          <CardDescription>
            {filteredSales.length} venda(s) encontrada(s)
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow className="border-green-200 hover:bg-green-50">
                  <TableHead className="text-gray-700 font-semibold">ID</TableHead>
                  <TableHead className="text-gray-700 font-semibold">Data/Hora</TableHead>
                  <TableHead className="text-gray-700 font-semibold">Atendente</TableHead>
                  <TableHead className="text-gray-700 font-semibold">Itens</TableHead>
                  <TableHead className="text-gray-700 font-semibold">Total</TableHead>
                  <TableHead className="text-gray-700 font-semibold">Ação</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredSales.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center text-gray-500 py-8">
                      <ShoppingCart className="w-8 h-8 mx-auto mb-2 opacity-50" />
                      <p>Nenhuma venda encontrada com os filtros aplicados</p>
                    </TableCell>
                  </TableRow>
                ) : (
                  filteredSales.map((sale) => (
                    <TableRow key={sale.id} className="border-green-100 hover:bg-green-50">
                      <TableCell className="font-medium text-gray-900">#{sale.id}</TableCell>
                      <TableCell className="text-gray-600 text-sm">{sale.date}</TableCell>
                      <TableCell className="text-gray-700">{sale.attendant}</TableCell>
                      <TableCell className="text-gray-600">{sale.itemCount} un</TableCell>
                      <TableCell className="font-semibold text-green-600">R$ {sale.total.toFixed(2)}</TableCell>
                      <TableCell>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => setExpandedSale(expandedSale === sale.id ? null : sale.id)}
                          className="text-green-600 hover:text-green-700 hover:bg-green-50"
                        >
                          <Eye className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>

          {/* Expanded Sale Details */}
          {expandedSale && (
            <div className="mt-6 border-t border-green-200 pt-6">
              {filteredSales.find((s) => s.id === expandedSale) && (
                <div className="bg-green-50 rounded-lg p-4">
                  <h4 className="font-semibold text-gray-900 mb-3">
                    Detalhes da Venda #{expandedSale}
                  </h4>
                  <div className="space-y-2">
                    {filteredSales
                      .find((s) => s.id === expandedSale)
                      ?.items.map((item, idx) => (
                        <div key={idx} className="flex justify-between text-sm text-gray-700 border-b border-green-200 pb-2">
                          <span>{item.productName}</span>
                          <span className="text-gray-600">
                            {item.quantity} un × R$ {item.price.toFixed(2)} = R$ {(item.quantity * item.price).toFixed(2)}
                          </span>
                        </div>
                      ))}
                  </div>
                </div>
              )}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

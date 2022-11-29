import{_ as E}from"./Authenticated.dcbcad7c.js";import{_ as U}from"./Button.2be174b5.js";import{r as G,a as F,T as x,_ as y,b as A,c as g}from"./TableHeadSort.61e109b2.js";import{_ as C}from"./SearchInput.e073f9df.js";import{_ as b}from"./MultiSelect.d357ce07.js";import{i as v,j as H,o as u,g as m,a as s,b as P,w as l,F as B,H as q,d as t,t as r,m as w,p as f,c as M,f as a,J as I,n as p}from"./app.e330703e.js";import"./open-closed.fedd291c.js";import"./use-resolve-button-type.9c7603ca.js";import"./RectangleStackIcon.a60b44a8.js";import"./_plugin-vue_export-helper.cdc0426e.js";const R=t("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Vending Machines (List) ",-1),z={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Y={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Q={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},W=a(" Vend ID "),X=a(" Serial Num "),Z=a(" Temp >> "),ee=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Errors? ",-1),te=a(" Cust ID "),ne=a(" Cust ID Name "),se=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),oe=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Group ",-1),le=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Online? ",-1),ae=t("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Customer Binded? ",-1),re={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},de={class:"mt-3"},ie={class:"flex space-x-1"},ue=t("span",null," Search ",-1),ce=t("span",null," Reset ",-1),me={class:"flex flex-col space-y-2"},_e={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=t("span",null,"Showing",-1),pe={class:"font-medium"},fe=t("span",null,"to",-1),xe={class:"font-medium"},he=t("span",null,"of",-1),ye={class:"font-medium"},be=t("span",null,"results",-1),ve={class:"mt-6 flex flex-col"},Ve={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Ce={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Be={class:"min-w-full border-separate",style:{"border-spacing":"0"}},ke={class:"bg-gray-100"},Se={class:"divide-x divide-gray-200"},Je=a(" # "),Te=a(" ID "),Pe=a(" Name "),we=a(" Category "),Ie=a(" Errors "),Le=a(" Inventory Status "),Ne=t("br",null,null,-1),je=a(" (#Channel, Sales, Balance/Capacity) "),Ke=a(" Temp "),Oe=t("br",null,null,-1),$e=a(" (Chamber) "),Ue=a(" Temp "),De=t("br",null,null,-1),Ee=a(" (Evaporator) "),Ge=a(" Balance Stock "),Fe=a(" Out of Stock SKU "),Ae=a(" Status "),He=a(" Serial Num "),qe=a(" Postcode "),Me=a(" Firmware Ver "),Re={class:"bg-white"},ze=t("br",null,null,-1),Ye=t("br",null,null,-1),Qe={class:"flex flex-col"},We={class:"font-bold"},Xe={class:"grid grid-cols-[105px_minmax(110px,_1fr)_100px]"},Ze={class:"font-semibold"},et={class:"text-blue-600 text-sm"},tt={class:"flex flex-col items-center"},nt=["onClick"],st={class:"mt-1"},ot={class:"flex flex-col items-center"},lt=["onClick"],at=t("br",null,null,-1),rt=t("br",null,null,-1),dt={class:"grid grid-cols-[90px_minmax(90px,_1fr)_90px] gap-1"},it={class:"flex flex-col"},ut={class:"font-bold"},ct={key:0},mt={class:"flex flex-col"},_t=t("span",{class:"font-bold"}," Drop Sensor ",-1),gt={key:1,class:"inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"},pt={class:"flex flex-col"},ft=t("span",{class:"font-bold"}," Fan Speed ",-1),xt={class:"flex flex-col"},ht=t("span",{class:"font-bold"}," Door ",-1),yt={class:"flex flex-col"},bt=t("span",{class:"font-bold"}," Coin ",-1),vt={key:0},Vt=t("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ct=[Vt],jt={__name:"Index",props:{categories:Object,categoryGroups:Object,constTempError:Number,countries:Object,vends:Object,vendChannelErrors:Object},setup(d){const k=d,n=v({code:"",serialNum:"",customer_code:"",customer_name:"",categories:[],categoryGroups:[],is_binded_customer:"",tempHigherThan:"",vend_channel_error_id:"",is_online:"",sortKey:"",sortBy:!0,numberPerPage:""}),S=v([]),J=v([]),L=v([]),N=v([]),V=v([]);H(()=>{S.value=[{id:"",desc:"All"},{id:"errors_only",desc:"Errors Only"},...k.vendChannelErrors.data],J.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.vend_channel_error_id=S.value[0],n.value.numberPerPage=J.value[0],L.value=k.categories.data.map(_=>({id:_.id,name:_.name})),N.value=k.categoryGroups.data.map(_=>({id:_.id,name:_.name})),V.value=[{id:"all",value:"All"},{id:"true",value:"Yes"},{id:"false",value:"No"}],n.value.is_online=V.value[1],n.value.is_binded_customer=V.value[1]});function T(){I.Inertia.get("/vends",{...n.value,vend_channel_error_id:n.value.vend_channel_error_id.id,categories:n.value.categories.map(_=>_.id),categoryGroups:n.value.categoryGroups.map(_=>_.id),is_binded_customer:n.value.is_binded_customer.id,is_online:n.value.is_online.id,numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0})}function j(_,o){I.Inertia.get("/vends/"+_+"/temp/"+o)}function D(){I.Inertia.get("/vends")}function h(_){n.value.sortKey=_,n.value.sortBy=!n.value.sortBy,T()}return(_,o)=>(u(),m(B,null,[s(P(q),{title:"Vending Machines"}),s(E,null,{header:l(()=>[R]),default:l(()=>{var K,O;return[t("div",z,[t("div",Y,[t("div",Q,[s(C,{placeholderStr:"Code",modelValue:n.value.code,"onUpdate:modelValue":o[0]||(o[0]=e=>n.value.code=e)},{default:l(()=>[W]),_:1},8,["modelValue"]),s(C,{placeholderStr:"Serial Num",modelValue:n.value.serialNum,"onUpdate:modelValue":o[1]||(o[1]=e=>n.value.serialNum=e)},{default:l(()=>[X]),_:1},8,["modelValue"]),s(C,{placeholderStr:"Number",modelValue:n.value.tempHigherThan,"onUpdate:modelValue":o[2]||(o[2]=e=>n.value.tempHigherThan=e)},{default:l(()=>[Z]),_:1},8,["modelValue"]),t("div",null,[ee,s(b,{modelValue:n.value.vend_channel_error_id,"onUpdate:modelValue":o[3]||(o[3]=e=>n.value.vend_channel_error_id=e),options:S.value,trackBy:"id",valueProp:"id",label:"desc",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),s(C,{placeholderStr:"Cust ID",modelValue:n.value.customer_code,"onUpdate:modelValue":o[4]||(o[4]=e=>n.value.customer_code=e)},{default:l(()=>[te]),_:1},8,["modelValue"]),s(C,{placeholderStr:"Cust ID Name",modelValue:n.value.customer_name,"onUpdate:modelValue":o[5]||(o[5]=e=>n.value.customer_name=e)},{default:l(()=>[ne]),_:1},8,["modelValue"]),t("div",null,[se,s(b,{modelValue:n.value.categories,"onUpdate:modelValue":o[6]||(o[6]=e=>n.value.categories=e),options:L.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[oe,s(b,{modelValue:n.value.categoryGroups,"onUpdate:modelValue":o[7]||(o[7]=e=>n.value.categoryGroups=e),options:N.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[le,s(b,{modelValue:n.value.is_online,"onUpdate:modelValue":o[8]||(o[8]=e=>n.value.is_online=e),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",null,[ae,s(b,{modelValue:n.value.is_binded_customer,"onUpdate:modelValue":o[9]||(o[9]=e=>n.value.is_binded_customer=e),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),t("div",re,[t("div",de,[t("div",ie,[s(U,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[10]||(o[10]=e=>T())},{default:l(()=>[s(P(G),{class:"h-4 w-4","aria-hidden":"true"}),ue]),_:1}),s(U,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[11]||(o[11]=e=>D())},{default:l(()=>[s(P(F),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),t("div",me,[t("p",_e,[ge,t("span",pe,r((K=d.vends.meta.from)!=null?K:0),1),fe,t("span",xe,r((O=d.vends.meta.to)!=null?O:0),1),he,t("span",ye,r(d.vends.meta.total),1),be]),s(b,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":o[12]||(o[12]=e=>n.value.numberPerPage=e),options:J.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:T},null,8,["modelValue","options"])])])]),t("div",ve,[t("div",Ve,[t("div",Ce,[t("table",Be,[t("thead",ke,[t("tr",Se,[s(x,null,{default:l(()=>[Je]),_:1}),s(y,{modelName:"vends.code",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[13]||(o[13]=e=>h("vends.code"))},{default:l(()=>[Te]),_:1},8,["sortKey","sortBy"]),s(x,null,{default:l(()=>[Pe]),_:1}),s(x,null,{default:l(()=>[we]),_:1}),s(x,null,{default:l(()=>[Ie]),_:1}),s(x,null,{default:l(()=>[Le,Ne,je]),_:1}),s(y,{modelName:"temp",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[14]||(o[14]=e=>h("temp"))},{default:l(()=>[Ke,Oe,$e]),_:1},8,["sortKey","sortBy"]),s(y,{modelName:"parameter_json->t2",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[15]||(o[15]=e=>h("parameter_json->t2"))},{default:l(()=>[Ue,De,Ee]),_:1},8,["sortKey","sortBy"]),s(y,{modelName:"vend_channel_totals_json->balancePercent",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[16]||(o[16]=e=>h("vend_channel_totals_json->balancePercent"))},{default:l(()=>[Ge]),_:1},8,["sortKey","sortBy"]),s(y,{modelName:"vend_channel_totals_json->outOfStockSkuPercent",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[17]||(o[17]=e=>h("vend_channel_totals_json->outOfStockSkuPercent"))},{default:l(()=>[Fe]),_:1},8,["sortKey","sortBy"]),s(x,null,{default:l(()=>[Ae]),_:1}),s(x,null,{default:l(()=>[He]),_:1}),s(y,{modelName:"addresses.postcode",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[18]||(o[18]=e=>h("addresses.postcode"))},{default:l(()=>[qe]),_:1},8,["sortKey","sortBy"]),s(x,null,{default:l(()=>[Me]),_:1})])]),t("tbody",Re,[(u(!0),m(B,null,w(d.vends.data,(e,c)=>(u(),m("tr",{key:c,class:"divide-x divide-gray-200"},[s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(d.vends.meta.from+c),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.code),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-left"},{default:l(()=>[a(r(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.code:null)+" ",1),ze,a(" "+r(e.latestVendBinding&&e.latestVendBinding.customer?e.latestVendBinding.customer.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-left"},{default:l(()=>[a(r(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category?e.latestVendBinding.customer.category.name:null)+" ",1),Ye,a(" "+r(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.category&&e.latestVendBinding.customer.category.category_group?e.latestVendBinding.customer.category.category_group.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[(u(!0),m(B,null,w(e.vendChannelErrorLogsJson,i=>(u(),m("span",{class:p(["inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border",[i.is_error_cleared?"bg-green-100 text-green-800":"bg-red-100 text-red-800"]])},[t("div",Qe,[t("div",null,[a(" #"+r(i.vendChannel?i.vendChannel.code:i.vend_channel.code)+", ",1),t("span",We," ("+r(i.vendChannelError?i.vendChannelError.code:i.vend_channel_error.code)+") ",1)]),t("div",null,r(i.created_at),1)])],2))),256))]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",Xe,[(u(!0),m(B,null,w(e.vendChannelsJson.filter(i=>i.code>=10&&i.code<=69),(i,$)=>(u(),m("span",{class:p(["inline-flex justify-evenly items-center rounded px-1 py-0.5 text-xs font-medium border min-w-full",[$>0&&String(i.code)[0]!==String(e.vendChannelsJson[$-1].code)[0]?"col-start-1":""]])},[t("div",Ze," #"+r(i.code)+", ",1),t("div",et,r(i.capacity-i.qty)+", ",1),t("div",{class:p([i.qty<=2?"text-red-700":"text-green-700"])},r(i.qty)+"/"+r(i.capacity),3)],2))),256))])]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",tt,[t("button",{type:"button",class:p(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:i=>j(e.id,1)},r(e.is_temp_error?"Error":e.temp),11,nt),t("span",st,r(e.temp_updated_at),1)])]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",ot,[e.parameterJson&&e.parameterJson.t2?(u(),m("button",{key:0,type:"button",class:p(["inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center",[e.temp>-15||e.parameterJson.t2==d.constTempError?"bg-red-400 active:bg-red-500 hover:bg-red-600":"bg-green-400 active:bg-green-500 hover:bg-green-600"]]),onClick:i=>j(e.id,2)},r(e.parameterJson.t2==d.constTempError?"Error":e.parameterJson.t2/10),11,lt)):f("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[e.vendChannelTotalsJson?(u(),m("span",{key:0,class:p([e.vendChannelTotalsJson.balancePercent<=30?"text-red-700":e.vendChannelTotalsJson.balancePercent>60?"":"text-blue-700"])},[a(r(e.vendChannelTotalsJson.qty)+"/ "+r(e.vendChannelTotalsJson.capacity)+" ",1),at,a(" ("+r(e.vendChannelTotalsJson.balancePercent)+"%) ",1)],2)):f("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[e.vendChannelTotalsJson?(u(),m("span",{key:0,class:p([e.vendChannelTotalsJson.outOfStockSkuPercent>40?"text-red-700":""])},[a(r(e.vendChannelTotalsJson.outOfStockSku)+"/ "+r(e.vendChannelTotalsJson.count)+" ",1),rt,a(" ("+r(e.vendChannelTotalsJson.outOfStockSkuPercent)+"%) ",1)],2)):f("",!0)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[t("div",dt,[t("div",{class:p(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.is_online?"bg-green-200":"bg-red-200"]])},[t("div",it,[t("span",ut,r(e.is_online?"Online":"Offline"),1),e.last_updated_at?(u(),m("span",ct,r(e.last_updated_at),1)):f("",!0)])],2),e.parameterJson&&e.parameterJson.Sensor?(u(),m("div",{key:0,class:p(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.Sensor==1?"bg-green-200":"bg-red-200"]])},[t("div",mt,[_t,t("span",null,r(e.parameterJson.Sensor==1?"Active":"Inactive"),1)])],2)):f("",!0),e.parameterJson&&e.parameterJson.fan?(u(),m("div",gt,[t("div",pt,[ft,t("span",null,r(e.parameterJson.fan),1)])])):f("",!0),e.parameterJson&&e.parameterJson.door?(u(),m("div",{key:2,class:p(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.door=="close"?"bg-green-200":"bg-red-200"]])},[t("div",xt,[ht,t("span",null,r(e.parameterJson.door=="open"?"Open":"Close"),1)])],2)):f("",!0),e.parameterJson&&e.parameterJson.CoinCnt?(u(),m("div",{key:3,class:p(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full",[e.parameterJson.CoinCnt>1600?"bg-green-200":"bg-red-200"]])},[t("div",yt,[bt,t("span",null,r((e.parameterJson.CoinCnt/100).toFixed(2)),1)])],2)):f("",!0)])]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.serial_num),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.latestVendBinding&&e.latestVendBinding.customer&&e.latestVendBinding.customer.deliveryAddress?e.latestVendBinding.customer.deliveryAddress.postcode:null),1)]),_:2},1032,["currentIndex","totalLength"]),s(g,{currentIndex:c,totalLength:d.vends.length,inputClass:"text-center"},{default:l(()=>[a(r(e.firmware_ver),1)]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.vends.data.length?f("",!0):(u(),m("tr",vt,Ct))])])]),d.vends.data.length?(u(),M(A,{key:0,links:d.vends.links,meta:d.vends.meta},null,8,["links","meta"])):f("",!0)])])])]}),_:1})],64))}};export{jt as default};

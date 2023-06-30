import{h as x,T as n,j as V,c as D,a as s,w as r,s as b,o as d,b as a,f as i,m as Y,t as C,d as m,u as _,e as M}from"./app.e5c92201.js";import{_ as v}from"./Button.3bbf2385.js";import{_ as y}from"./DatePicker.404d4d20.js";import{_ as $}from"./FormInput.c4170332.js";import{_ as w}from"./FormTextarea.d1b464a2.js";import{_ as S,r as k}from"./Modal.f7b1f892.js";import{r as B}from"./ArrowUturnLeftIcon.a018af90.js";import"./main.c28be5a1.js";import"./open-closed.447394b4.js";const N={class:"flex flex-col md:flex-row space-x-2"},F={key:0,class:"text-gray-600"},T={key:1},U={key:2,class:"text-gray-600"},j=["onSubmit"],E={class:"grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6"},q={class:"sm:col-span-6"},H={class:"sm:col-span-6"},I={class:"sm:col-span-6"},O={class:"sm:col-span-6"},z={class:"sm:col-span-6"},A={class:"flex space-x-1 mt-5 justify-end"},G=a("span",null," Back ",-1),J=a("span",null," Save ",-1),oe={__name:"Form",props:{holiday:Object,type:String,showModal:Boolean},emits:["modalClose"],setup(u,{emit:c}){const l=u,e=x(n(f()));V(()=>{e.value=l.holiday?n(l.holiday):n(f()),e.value.date_from=moment().format("YYYY-MM-DD"),e.value.date_to=moment().format("YYYY-MM-DD")});function f(){return{name:"",date_from:"",date_to:"",desc:""}}function g(){console.log("herer"),e.value.date_from&&(e.value.date_to=moment(e.value.date_from).format("YYYY-MM-DD"))}function h(){e.value.clearErrors(),l.type==="create"&&e.value.post("/holidays/create",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0}),l.type==="update"&&e.value.post("/holidays/"+e.value.id+"/update",{onSuccess:()=>{c("modalClose")},preserveState:!0,replace:!0})}return(p,o)=>(d(),D(b,{to:"body"},[s(S,{open:u.showModal,onModalClose:o[6]||(o[6]=t=>p.$emit("modalClose"))},{header:r(()=>[a("div",N,[l.holiday?(d(),i("span",F," Editing ")):Y("",!0),l.holiday?(d(),i("span",T,C(l.holiday.name),1)):(d(),i("span",U," Create New Holiday "))])]),default:r(()=>[a("form",{onSubmit:M(h,["prevent"]),id:"submit"},[a("div",E,[a("div",q,[s($,{modelValue:e.value.name,"onUpdate:modelValue":o[0]||(o[0]=t=>e.value.name=t),error:e.value.errors.name,required:"true"},{default:r(()=>[m(" Name ")]),_:1},8,["modelValue","error"])]),a("div",H,[s(y,{modelValue:e.value.date_from,"onUpdate:modelValue":o[1]||(o[1]=t=>e.value.date_from=t),error:e.value.errors.date_from,onInput:o[2]||(o[2]=t=>g())},{default:r(()=>[m(" Date From ")]),_:1},8,["modelValue","error"])]),a("div",I,[s(y,{modelValue:e.value.date_to,"onUpdate:modelValue":o[3]||(o[3]=t=>e.value.date_to=t),error:e.value.errors.date_to,minDate:e.value.date_from},{default:r(()=>[m(" Date To ")]),_:1},8,["modelValue","error","minDate"])]),a("div",O,[s(w,{modelValue:e.value.desc,"onUpdate:modelValue":o[4]||(o[4]=t=>e.value.desc=t),error:e.value.errors.desc},{default:r(()=>[m(" Desc ")]),_:1},8,["modelValue","error"])])]),a("div",z,[a("div",A,[s(v,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1",onClick:o[5]||(o[5]=t=>p.$emit("modalClose")),form:"submit"},{default:r(()=>[s(_(B),{class:"w-4 h-4"}),G]),_:1}),s(v,{type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:r(()=>[s(_(k),{class:"w-4 h-4"}),J]),_:1})])])],40,j)]),_:1},8,["open"])]))}};export{oe as default};
